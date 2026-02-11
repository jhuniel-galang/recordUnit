<!DOCTYPE html>
<html>
<head>
    <title>Secure Document Viewer</title>
    <style>
        body { margin: 0; padding: 20px; background: #f0f0f0; }
        .secure-frame {
            width: 100%;
            height: 90vh;
            border: 2px solid #2E8B57;
            background: white;
        }
        .watermark {
            position: fixed;
            opacity: 0.1;
            font-size: 50px;
            color: red;
            transform: rotate(-45deg);
            pointer-events: none;
        }
    </style>
</head>
<body>
    <!-- Watermark -->
    <div class="watermark">VIEW ONLY - DO NOT DELETE</div>
    
    <?php if (pathinfo($_GET['file'], PATHINFO_EXTENSION) === 'pdf'): ?>
        <!-- PDF Viewer -->
        <iframe 
            class="secure-frame"
            src="server.php?file=<?php echo urlencode($_GET['file']); ?>"
            title="Secure Document View">
            Your browser does not support PDFs.
        </iframe>
    <?php else: ?>
        <!-- Image Viewer -->
        <div style="text-align: center; background: white; padding: 20px;">
            <img 
                src="server.php?file=<?php echo urlencode($_GET['file']); ?>" 
                style="max-width: 100%; max-height: 80vh; border: 1px solid #ccc;"
                alt="Document"
                oncontextmenu="return false;">
        </div>
    <?php endif; ?>
    
    <div style="text-align: center; margin-top: 10px;">
        <button onclick="window.print()">Print</button>
        <button onclick="window.close()">Close Window</button>
        <span style="color: red; margin-left: 20px; font-weight: bold;">
            ⚠️ This is a secure view. Deletion is disabled.
        </span>
    </div>
    
    <script>
        // Disable all keyboard shortcuts except print (Ctrl+P)
        document.addEventListener('keydown', function(e) {
            // Block Ctrl+S (Save), F12 (Dev Tools), etc.
            const blockedKeys = [
                {ctrl: true, key: 's'},
                {ctrl: true, shift: true, key: 'i'},
                {key: 'F12'},
                {ctrl: true, shift: true, key: 'j'},
                {ctrl: true, key: 'u'}
            ];
            
            blockedKeys.forEach(block => {
                if ((block.ctrl === undefined || block.ctrl === e.ctrlKey) &&
                    (block.shift === undefined || block.shift === e.shiftKey) &&
                    e.key.toLowerCase() === block.key.toLowerCase()) {
                    e.preventDefault();
                    alert('This action is disabled for security.');
                }
            });
        });
    </script>
</body>
</html>