<?php

namespace App\Http\Controllers;

use App\Models\ScormPackage;
use App\Models\ScormSco;
use App\Models\ScormTracking;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use ZipArchive;
use SimpleXMLElement;
use Illuminate\Support\Facades\File;
use DB;

class ScormController extends Controller
{
    /**
     * Show upload form + list.
     */
    public function index()
    {
        $packages = ScormPackage::with('scos')->latest()->get();
        return view('scorm.index', compact('packages'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'scorm_file' => 'required|mimes:zip|max:51200',
        ]);
        $fullZipPath = null;
        $extractPath = null;
        try {
            DB::beginTransaction();
            $zipFile = $request->file('scorm_file');
            $zipName = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $zipFile->getClientOriginalName());

            // Store the uploaded ZIP file
            $zipPath = $zipFile->storeAs('scorm_packages', $zipName, 'public');

            if (!$zipPath) {
                return back()->with('error', 'Failed to store ZIP file.');
            }

            $fullZipPath = storage_path('app/public/' . $zipPath);

            if (!file_exists($fullZipPath)) {
                return back()->with('error', 'ZIP file was not stored correctly.');
            }

            // Create extraction folder
            $extractFolder = 'scorm_packages/' . uniqid('pkg_');
            $extractPath = storage_path('app/public/' . $extractFolder);

            if (!file_exists($extractPath)) {
                if (!mkdir($extractPath, 0755, true)) {
                    return back()->with('error', 'Failed to create extraction directory.');
                }
            }

            // Extract ZIP file
            $zip = new ZipArchive;
            $zipStatus = $zip->open($fullZipPath);

            if ($zipStatus === true) {
                $extractionResult = $zip->extractTo($extractPath);
                $zip->close();

                if (!$extractionResult) {
                    return back()->with('error', 'Failed to extract ZIP file contents.');
                }
            } else {
                $errorMessages = [
                    ZipArchive::ER_EXISTS => 'File already exists.',
                    ZipArchive::ER_INCONS => 'Zip archive inconsistent.',
                    ZipArchive::ER_INVAL => 'Invalid argument.',
                    ZipArchive::ER_MEMORY => 'Malloc failure.',
                    ZipArchive::ER_NOENT => 'No such file.',
                    ZipArchive::ER_NOZIP => 'Not a zip archive.',
                    ZipArchive::ER_OPEN => "Can't open file.",
                    ZipArchive::ER_READ => 'Read error.',
                    ZipArchive::ER_SEEK => 'Seek error.',
                ];

                $errorMessage = $errorMessages[$zipStatus] ?? "Failed to open ZIP file (Error code: $zipStatus)";
                return back()->with('error', $errorMessage);
            }

            // Verify extraction was successful
            $files = array_diff(scandir($extractPath), ['.', '..']);
            if (empty($files)) {
                return back()->with('error', 'Extraction failed — ZIP may be empty or invalid.');
            }

            // Locate manifest file
            $manifestFile = $this->findManifest($extractPath);
            if (!$manifestFile) {
                return back()->with('error', 'imsmanifest.xml not found in extracted files.');
            }

            // Parse manifest XML
            $manifestXml = simplexml_load_file($manifestFile);
            if (!$manifestXml) {
                return back()->with('error', 'Failed to parse imsmanifest.xml file.');
            }

            // Detect SCORM version and get package info
            $version = $this->detectScormVersion($manifestXml);
            $packageInfo = $this->getPackageInfo($manifestXml);
            $package = ScormPackage::create([
                'title' => $packageInfo['title'],
                'identifier' => $packageInfo['identifier'],
                'version' => $version,
                'file_path' => $extractFolder,
                'entry_point' => $this->findEntryPoint($manifestXml, $extractPath, $version),
            ]);

            // Save all SCOs with proper hierarchy
            $this->saveScos($manifestXml, $package, $version);

            DB::commit();
            if ($fullZipPath && file_exists($fullZipPath)) {
                unlink($fullZipPath);
            }
            return redirect()->route('scorm.index')->with('success', 'SCORM package uploaded successfully.');

        } catch (\Exception $e) {
            \Log::error('SCORM Upload Error: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            DB::rollBack();
            if (isset($fullZipPath) && file_exists($fullZipPath)) {
                unlink($fullZipPath);
            }
            if (isset($extractPath) && file_exists($extractPath)) {
                $this->deleteDirectory($extractPath);
            }
            return back()->with('error', 'An unexpected error occurred: ' . $e->getMessage());
        }
    }

    /**
     * Detect SCORM version from manifest
     */
    private function detectScormVersion(SimpleXMLElement $manifest)
    {
        $namespaces = $manifest->getNamespaces(true);

        // Check metadata schema version
        $schemaVersion = (string) ($manifest->metadata->schemaversion ?? '');
        $schema = (string) ($manifest->metadata->schema ?? '');

        // SCORM 2004 indicators
        if (
            strpos($schemaVersion, '2004') !== false ||
            strpos($schemaVersion, 'CAM 1.3') !== false ||
            strpos($schemaVersion, '4th') !== false ||
            strpos($schema, '2004') !== false
        ) {
            return '2004';
        }

        // SCORM 1.2 indicators
        if (
            strpos($schemaVersion, '1.2') !== false ||
            strpos($schema, '1.2') !== false ||
            strpos($schema, 'SCORM') !== false
        ) {
            return '1.2';
        }

        // Check for ADLCP namespace (common in SCORM 2004)
        foreach ($namespaces as $prefix => $uri) {
            if (strpos($uri, 'adlnet') !== false) {
                if (strpos($uri, '2004') !== false) {
                    return '2004';
                } else {
                    return '1.2';
                }
            }
        }
        // Default based on common patterns
        if (!empty($schemaVersion) || !empty($schema)) {
            return '1.2';
        }
        return '1.2';
    }

    /**
     * Get package information from manifest
     */
    private function getPackageInfo(SimpleXMLElement $manifest)
    {
        $defaultOrgId = (string) $manifest->organizations['default'] ?? '';
        $organizations = $manifest->organizations->organization ?? [];

        $title = 'Untitled SCORM';
        $identifier = uniqid('scorm_');

        // Find the default organization
        foreach ($organizations as $org) {
            $orgId = (string) $org['identifier'];
            if ($orgId === $defaultOrgId || $title === 'Untitled SCORM') {
                $title = (string) ($org->title ?? $title);
                $identifier = (string) ($org['identifier'] ?? $identifier);

                if ($orgId === $defaultOrgId) {
                    break;
                }
            }
        }

        return [
            'title' => $title,
            'identifier' => $identifier,
        ];
    }

    /**
     * Helper to find imsmanifest.xml recursively
     */
    private function findManifest($path)
    {
        // Check root first
        $rootManifest = $path . '/imsmanifest.xml';
        if (file_exists($rootManifest)) {
            return $rootManifest;
        }

        // Search recursively
        $rii = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path));
        foreach ($rii as $file) {
            if (!$file->isDir() && $file->getFilename() === 'imsmanifest.xml') {
                return $file->getPathname();
            }
        }
        return null;
    }

    /**
     * Find the entry point for the SCORM package
     */
    private function findEntryPoint(SimpleXMLElement $manifest, $basePath, $version = '1.2')
    {
        $defaultOrgId = (string) $manifest->organizations['default'] ?? '';
        $organizations = $manifest->organizations->organization ?? [];

        $targetOrganization = null;
        // Find the default organization
        foreach ($organizations as $org) {
            $orgId = (string) $org['identifier'];
            if ($orgId === $defaultOrgId) {
                $targetOrganization = $org;
                break;
            }
        }

        // Fallback to first organization
        if (!$targetOrganization && count($organizations) > 0) {
            $targetOrganization = $organizations[0];
        }

        if ($targetOrganization) {
            // Find first launchable SCO in the organization
            $firstSco = $this->findFirstLaunchableSco($targetOrganization, $manifest, $version);
            if ($firstSco) {
                // Check if file exists
                if (file_exists($basePath . '/' . $firstSco)) {
                    return $firstSco;
                }

                // Try alternative paths
                $possiblePaths = [
                    $basePath . '/' . $firstSco,
                    $basePath . '/' . ltrim($firstSco, '/'),
                    $basePath . '/' . dirname($firstSco) . '/' . basename($firstSco)
                ];

                foreach ($possiblePaths as $possiblePath) {
                    if (file_exists($possiblePath)) {
                        return str_replace($basePath . '/', '', $possiblePath);
                    }
                }

                \Log::warning("Entry point file not found: " . $firstSco);
            } else {
                \Log::warning("No launchable SCO found in organization");
            }
        }

        // Fallback: look for common entry points
        $commonEntryPoints = ['index.html', 'index.htm', 'launch.html', 'start.html'];
        foreach ($commonEntryPoints as $entry) {
            if (file_exists($basePath . '/' . $entry)) {
                \Log::info("Using fallback entry point: " . $entry);
                return $entry;
            }
        }
        return 'index.html';
    }

    /**
     * Find first launchable SCO in organization (recursive)
     */
    private function findFirstLaunchableSco(SimpleXMLElement $organization, SimpleXMLElement $manifest, $version = '1.2')
    {
        $items = $organization->item ?? [];
        foreach ($items as $item) {
            $identifierRef = (string) $item['identifierref'];

            // If this item has a resource reference, check if it's a SCO
            if ($identifierRef) {
                // Find the resource
                $resource = $this->findResourceByIdentifier($manifest, $identifierRef);
                if ($resource) {
                    $scormType = (string) $resource['adlcp:scormtype'] ?? '';
                    $href = (string) $resource['href'] ?? '';

                    // Check if it's launchable based on version
                    $isLaunchable = false;
                    if ($version === '2004') {
                        $isLaunchable = ($scormType === 'sco' && $href);
                    } else {
                        // SCORM 1.2 - all resources with href are potentially launchable
                        $isLaunchable = ($href && ($scormType === 'sco' || $scormType === ''));
                    }

                    if ($isLaunchable) {
                        return $href;
                    }
                }
            }

            // Recursively check child items
            if (isset($item->item)) {
                $childResult = $this->findFirstLaunchableSco($item, $manifest, $version);
                if ($childResult) {
                    return $childResult;
                }
            }
        }

        return null;
    }

    /**
     * Find resource by identifier
     */
    private function findResourceByIdentifier(SimpleXMLElement $manifest, $identifier)
    {
        if (!$manifest->resources) {
            return null;
        }
        foreach ($manifest->resources->resource as $resource) {
            $resourceId = (string) $resource['identifier'];
            if ($resourceId === $identifier) {
                return $resource;
            }
        }
        return null;
    }

    /**
     * Save all SCOs from the manifest with proper hierarchy
     */
    private function saveScos(SimpleXMLElement $manifest, ScormPackage $package, $version = '1.2')
    {
        $defaultOrgId = (string) $manifest->organizations['default'] ?? '';
        $organizations = $manifest->organizations->organization ?? [];

        foreach ($organizations as $organization) {
            $orgId = (string) $organization['identifier'];

            // Process only the default organization, or all if no default specified
            if (!$defaultOrgId || $orgId === $defaultOrgId) {
                $items = $organization->item ?? [];
                if ($items) {
                    $this->processItemsRecursive($items, $package, null, $manifest, $version, 0);
                }
                break;
            }
        }
    }

    /**
     * Process items recursively with proper hierarchy
     */
    private function processItemsRecursive($items, ScormPackage $package, $parentId = null, SimpleXMLElement $manifest, $version = '1.2', $level = 0)
    {
        if (!$items)
            return;

        // Handle both single items and arrays
        // $itemsToProcess = [];
        // if ($items instanceof SimpleXMLElement) {
        //     $itemsToProcess = [$items];
        // } else {
        //     $itemsToProcess = $items;
        // }
        $itemsToProcess = $items;

        $sortOrder = 0;

        foreach ($itemsToProcess as $item) {
            if (!$item instanceof SimpleXMLElement)
                continue;

            $identifier = (string) $item['identifier'];
            $title = (string) $item->title;
            $identifierRef = (string) $item['identifierref'];
            $launchUrl = null;
            $isSco = false;
            // Find resource and determine if it's a SCO
            if ($identifierRef) {
                $resource = $this->findResourceByIdentifier($manifest, $identifierRef);
                if ($resource) {
                    $scormType = (string) $resource['adlcp:scormtype'] ?? '';
                    $href = (string) $resource['href'] ?? '';

                    \Log::info("Processing item {$title}: scormType={$scormType}, href={$href}");

                    // Determine if this is a SCO based on version
                    if ($version === '2004') {
                        $isSco = ($scormType === 'sco' && $href);
                    } else {
                        // SCORM 1.2 - all resources with href are considered SCOs
                        $isSco = ($href && ($scormType === 'sco' || $scormType === ''));
                    }
                    if ($isSco) {
                        $launchUrl = $href;
                        $parameters = (string) $item['parameters'] ?? '';
                        if ($parameters) {
                            $launchUrl .= $parameters;
                        }
                        \Log::info("Item {$title} is SCO with launch URL: {$launchUrl}");
                    }
                } else {
                    \Log::warning("Resource not found for identifier: " . $identifierRef);
                }
            }

            \Log::info("Creating SCO: Level {$level}, Title: {$title}, IsSCO: " . ($isSco ? 'YES' : 'NO') . ", LaunchURL: " . ($launchUrl ?? 'NULL'));

            // Create SCO record (create both SCOs and containers)
            $sco = ScormSco::create([
                'scorm_package_id' => $package->id,
                'identifier' => $identifier,
                'title' => $title,
                'launch' => $launchUrl,
                'sort_order' => $sortOrder++,
                'parent_id' => $parentId,
                'is_launchable' => $isSco,
            ]);
            // Process child items recursively
            if (isset($item->item)) {
                $this->processItemsRecursive($item->item, $package, $sco->id, $manifest, $version, $level + 1);
            }
        }
    }

    /**
     * Recursively delete directory (only for cleanup on failure)
     */
    private function deleteDirectory($path)
    {
        if (!file_exists($path)) {
            return true;
        }

        if (!is_dir($path)) {
            return unlink($path);
        }

        foreach (scandir($path) as $item) {
            if ($item == '.' || $item == '..') {
                continue;
            }

            if (!$this->deleteDirectory($path . DIRECTORY_SEPARATOR . $item)) {
                return false;
            }
        }

        return rmdir($path);
    }

    public function outline(ScormPackage $package)
    {
        $scos = $package->scos()->with('children')->whereNull('parent_id')->orderBy('sort_order')->get();
        return view('scorm.outline', compact('package', 'scos'));
    }

    // public function serveContent(ScormPackage $package, $path)
    // {
    //     $path = ltrim($path, '/');
    //     $filePath = storage_path('app/public/' . $package->file_path . '/' . $path);

    //     if (!file_exists($filePath)) {
    //         abort(404, 'SCORM file not found: ' . $path);
    //     }

    //     // Get HTML content
    //     $html = file_get_contents($filePath);

    //     // Inject <base> tag to fix relative paths
    //     $baseUrl = asset('storage/' . $package->file_path . '/' . dirname($path));


    //     if (str_ends_with($path, '.html')) {
    //         $baseUrl = asset('storage/' . $package->file_path . '/' . dirname($path));
    //         $html = preg_replace('/<head>/i', '<head><base href="' . $baseUrl . '/">', $html);
    //     }
    //     return response($html)->header('Content-Type', 'text/html');
    // }

    public function serveContent(ScormPackage $package, $path)
    {
        $path = ltrim($path, '/');
        $fullPath = storage_path('app/public/' . $package->file_path . '/' . $path);

        if (!file_exists($fullPath)) {
            abort(404, 'SCORM file not found: ' . $path);
        }

        $ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));

        // Serve static files (CSS, JS, images)
        if (!in_array($ext, ['html', 'htm'])) {
            return response()->file($fullPath, [
                'Content-Type' => mime_content_type($fullPath) ?: 'application/octet-stream',
            ]);
        }

        // Load HTML
        $html = file_get_contents($fullPath);

        // Ensure <base> is set
        $baseDir = dirname($path);
        $baseUrl = asset('storage/' . trim($package->file_path . '/' . $baseDir, '/')) . '/';
        $html = preg_replace('/<head[^>]*>/i', '<head><base href="' . e($baseUrl) . '">', $html, 1);

        return response($html)->header('Content-Type', 'text/html');
    }








}
