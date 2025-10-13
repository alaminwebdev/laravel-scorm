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
            return redirect()->route('scorm.index')->with('success', 'SCORM package uploaded successfully.');

        } catch (\Exception $e) {
            \Log::error('SCORM Upload Error: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            DB::rollBack();
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

        \Log::info("SCORM Version Detection - Schema: {$schema}, SchemaVersion: {$schemaVersion}");

        // SCORM 2004 indicators
        if (
            strpos($schemaVersion, '2004') !== false ||
            strpos($schemaVersion, 'CAM 1.3') !== false ||
            strpos($schemaVersion, '4th') !== false ||
            strpos($schema, '2004') !== false
        ) {
            \Log::info("Detected SCORM 2004");
            return '2004';
        }

        // SCORM 1.2 indicators
        if (
            strpos($schemaVersion, '1.2') !== false ||
            strpos($schema, '1.2') !== false ||
            strpos($schema, 'SCORM') !== false
        ) {
            \Log::info("Detected SCORM 1.2");
            return '1.2';
        }

        // Check for ADLCP namespace (common in SCORM 2004)
        foreach ($namespaces as $prefix => $uri) {
            if (strpos($uri, 'adlnet') !== false) {
                if (strpos($uri, '2004') !== false) {
                    \Log::info("Detected SCORM 2004 via namespace");
                    return '2004';
                } else {
                    \Log::info("Detected SCORM 1.2 via namespace");
                    return '1.2';
                }
            }
        }

        // Default based on common patterns
        if (!empty($schemaVersion) || !empty($schema)) {
            \Log::info("Defaulting to SCORM 1.2 based on metadata presence");
            return '1.2';
        }

        \Log::info("No version detected, defaulting to SCORM 1.2");
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
                \Log::info("Found entry point: " . $firstSco);

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
                        \Log::info("Entry point found at: " . $possiblePath);
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

        \Log::warning("No entry point found, using default index.html");
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

                    \Log::info("Checking resource: " . $identifierRef . " -> " . $scormType . " -> " . $href);

                    // Check if it's launchable based on version
                    $isLaunchable = false;
                    if ($version === '2004') {
                        $isLaunchable = ($scormType === 'sco' && $href);
                    } else {
                        // SCORM 1.2 - all resources with href are potentially launchable
                        $isLaunchable = ($href && ($scormType === 'sco' || $scormType === ''));
                    }

                    if ($isLaunchable) {
                        \Log::info("Found launchable SCO: " . $href);
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
            // dd($item->item, $itemsToProcess);
            // Process child items recursively
            if (isset($item->item)) {
                $this->processItemsRecursive($item->item, $package, $sco->id, $manifest, $version, $level + 1);
            }
        }
    }

    /**
     * Show SCORM course outline with user progress
     */
    public function outline($id)
    {
        $package = ScormPackage::with('scos')->findOrFail($id);
        $userId = auth()->id();

        // Get user tracking for this package
        $tracking = ScormTracking::whereIn('scorm_sco_id', $package->scos->pluck('id'))
            ->where('user_id', $userId)
            ->get()
            ->keyBy('scorm_sco_id');

        return view('scorm.outline', compact('package', 'tracking'));
    }

    // public function launch($id, Request $request)
    // {
    //     $package = ScormPackage::with('scos')->findOrFail($id);

    //     // Get SCO ID from query, fallback to first SCO
    //     $scoId = $request->query('sco') ?? $package->scos->first()?->id;
    //     $sco = $package->scos->find($scoId);
    //     // Determine launch path
    //     if ($sco && $sco->launch) {
    //         $filePath = storage_path('app/public/' . $package->file_path . '/' . $sco->launch);
    //         if (!file_exists($filePath)) {
    //             return redirect()->route('scorm.index')->with('error', 'Launch file not found.');
    //         }
    //         $launchPath = asset('storage/' . $package->file_path . '/' . $sco->launch);
    //     } else {
    //         $defaultFile = storage_path('app/public/' . $package->file_path . '/' . $package->entry_point);
    //         if (!file_exists($defaultFile)) {
    //             return redirect()->route('scorm.index')->with('error', 'Entry point file not found.');
    //         }
    //         $launchPath = asset('storage/' . $package->file_path . '/' . $package->entry_point);
    //     }

    //     // Determine SCORM version JS
    //     $apiJs = $package->version === '2004' ? 'scorm2004.js' : 'scorm.js';
    //     return view('scorm.launch', compact('package', 'launchPath', 'apiJs', 'scoId'));
    // }

    public function launch($id, Request $request)
    {
        try {
            $package = ScormPackage::with([
                'scos' => function ($query) {
                    $query->where('is_launchable', true)->orderBy('sort_order');
                }
            ])->findOrFail($id);

            // Get SCO ID from query, fallback to first launchable SCO
            $scoId = $request->query('sco');
            $sco = $scoId ? $package->scos->find($scoId) : $package->scos->first();

            // If no SCO found, use entry point
            if (!$sco) {
                return $this->launchWithEntryPoint($package);
            }

            // Get launch path
            $launchPath = $this->getValidLaunchPath($package, $sco->launch);
            if (!$launchPath) {
                // Fallback to entry point
                return $this->launchWithEntryPoint($package);
            }

            // Get navigation
            $navigation = $this->getNavigation($package, $sco);

            $apiJs = $package->version === '2004' ? 'scorm2004.js' : 'scorm.js';

            return view('scorm.launch', compact('package', 'launchPath', 'apiJs', 'sco', 'navigation'));

        } catch (\Exception $e) {
            return redirect()->route('scorm.index')->with('error', 'Error launching SCORM: ' . $e->getMessage());
        }
    }

    private function launchWithEntryPoint($package)
    {
        $launchPath = $this->getValidLaunchPath($package, $package->entry_point);
        if (!$launchPath) {
            return redirect()->route('scorm.index')->with('error', 'No launchable content found.');
        }

        $apiJs = $package->version === '2004' ? 'scorm2004.js' : 'scorm.js';
        return view('scorm.launch', compact('package', 'launchPath', 'apiJs'));
    }

    private function getValidLaunchPath($package, $filePath)
    {
        if (!$filePath) {
            return null;
        }

        $cleanPath = ltrim($filePath, '/');
        $fullPath = storage_path('app/public/' . $package->file_path . '/' . $cleanPath);

        if (file_exists($fullPath)) {
            return asset('storage/' . $package->file_path . '/' . $cleanPath);
        }

        return null;
    }

    private function getNavigation($package, $currentSco)
    {
        $launchableScos = $package->scos->where('is_launchable', true)->sortBy('sort_order');
        $scoArray = $launchableScos->values();
        $currentIndex = $scoArray->search(function ($sco) use ($currentSco) {
            return $sco->id === $currentSco->id;
        });

        return [
            'previous' => $currentIndex > 0 ? $scoArray[$currentIndex - 1] : null,
            'next' => $currentIndex < ($scoArray->count() - 1) ? $scoArray[$currentIndex + 1] : null,
            'total' => $scoArray->count(),
            'current' => $currentIndex + 1,
        ];
    }

}
