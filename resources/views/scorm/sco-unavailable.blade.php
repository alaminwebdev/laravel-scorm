<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Content Unavailable</title>
    <style>
        body {
            margin: 0;
            padding: 40px;
            font-family: Arial, sans-serif;
            background: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }

        .error-container {
            background: white;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            text-align: center;
            max-width: 500px;
        }

        .error-icon {
            font-size: 48px;
            margin-bottom: 20px;
        }
    </style>
</head>

<body>
    <div class="error-container">
        <div class="error-icon">⚠️</div>
        <h1>Content Unavailable</h1>
        <p>The requested learning content "{{ $sco->title }}" is not available.</p>
        <p>This may be due to:</p>
        <ul style="text-align: left; margin: 20px 0;">
            <li>Missing content files</li>
            <li>Incorrect file paths</li>
            <li>Package extraction issues</li>
        </ul>
        <button onclick="window.parent.postMessage({action: 'closeSco'}, '*')" style="background: #007bff; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer;">
            Return to Course
        </button>
    </div>

    <script>
        // Notify parent window that this SCO failed to load
        window.parent.postMessage({
            action: 'scoLoadError',
            scoId: {{ $sco->id }},
            message: 'Content unavailable'
        }, '*');
    </script>
</body>

</html>
