<?php
session_start();
require 'config.php';
require 'auth.php';
requireLogin();

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Get document info
$stmt = $conn->prepare("
    SELECT d.*, u.name AS uploader_name 
    FROM documents d
    JOIN users u ON d.user_id = u.id
    WHERE d.id = ? AND d.status = 1
");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Document not found or access denied.");
}

$doc = $result->fetch_assoc();

// Check if user has permission (admin or owner)
if (!isAdmin() && $doc['user_id'] != $_SESSION['user_id']) {
    die("You don't have permission to view this document.");
}

// Log the preview action
$logStmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, description) VALUES (?, 'preview', ?)");
$description = "Previewed document: " . $doc['file_name'];
$logStmt->bind_param("is", $_SESSION['user_id'], $description);
$logStmt->execute();

// Get file extension
$file_ext = strtolower(pathinfo($doc['file_name'], PATHINFO_EXTENSION));
$is_pdf = ($file_ext === 'pdf');
$is_image = in_array($file_ext, ['jpg', 'jpeg', 'png', 'gif', 'bmp']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Preview: <?= htmlspecialchars($doc['file_name']) ?> | Record Management System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #4361ee;
            --primary-dark: #3a56d4;
            --secondary: #7209b7;
            --success: #4cc9f0;
            --warning: #f72585;
            --dark: #212529;
            --gray: #6c757d;
            --light-gray: #e9ecef;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #1a1a2e;
            height: 100vh;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        /* Header */
        .preview-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            padding: 0.75rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.3);
            z-index: 10;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .back-btn {
            background: rgba(255,255,255,0.2);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 25px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .back-btn:hover {
            background: rgba(255,255,255,0.3);
            transform: translateX(-3px);
        }

        .file-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .file-info i {
            font-size: 1.5rem;
        }

        .file-details h2 {
            font-size: 1.2rem;
            margin-bottom: 4px;
        }

        .file-details p {
            font-size: 0.85rem;
            opacity: 0.9;
        }

        .security-badge {
            background: rgba(0,0,0,0.3);
            padding: 8px 16px;
            border-radius: 25px;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.9rem;
            border: 1px solid rgba(255,255,255,0.2);
        }

        .security-badge i {
            color: #4cc9f0;
        }

        /* Main Content */
        .preview-container {
            flex: 1;
            display: flex;
            position: relative;
            background: #0f0f1a;
        }

        /* PDF Viewer */
        .pdf-viewer {
            flex: 1;
            position: relative;
            background: #2d2d3a;
        }

        #pdfCanvas {
            display: block;
            margin: 0 auto;
            box-shadow: 0 0 30px rgba(0,0,0,0.5);
            background: white;
        }

        .pdf-controls {
            position: fixed;
            bottom: 30px;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(33, 33, 52, 0.95);
            backdrop-filter: blur(10px);
            padding: 12px 24px;
            border-radius: 50px;
            display: flex;
            gap: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
            border: 1px solid rgba(255,255,255,0.1);
            z-index: 20;
        }

        .pdf-controls button {
            background: none;
            border: none;
            color: white;
            cursor: pointer;
            padding: 8px 16px;
            border-radius: 25px;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            font-size: 0.9rem;
        }

        .pdf-controls button:hover {
            background: rgba(67, 97, 238, 0.3);
        }

        .pdf-controls button:disabled {
            opacity: 0.3;
            cursor: not-allowed;
        }

        .page-info {
            color: white;
            padding: 8px 20px;
            background: rgba(255,255,255,0.1);
            border-radius: 25px;
            font-weight: 600;
        }

        /* Image Viewer */
        .image-viewer {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #0f0f1a;
            padding: 20px;
        }

        .secure-image {
            max-width: 90%;
            max-height: 90vh;
            object-fit: contain;
            box-shadow: 0 0 50px rgba(0,0,0,0.5);
            border-radius: 8px;
        }

        /* Watermark */
        .watermark {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 100px;
            font-weight: bold;
            color: rgba(255,255,255,0.03);
            white-space: nowrap;
            pointer-events: none;
            z-index: 5;
            text-transform: uppercase;
            letter-spacing: 10px;
        }

        /* Info Panel */
        .info-panel {
            width: 300px;
            background: rgba(26, 26, 46, 0.95);
            backdrop-filter: blur(10px);
            border-left: 1px solid rgba(255,255,255,0.1);
            color: white;
            padding: 20px;
            overflow-y: auto;
        }

        .info-panel h3 {
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            padding-bottom: 10px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .info-item {
            margin-bottom: 15px;
        }

        .info-label {
            font-size: 0.8rem;
            color: #a0a0c0;
            margin-bottom: 5px;
        }

        .info-value {
            background: rgba(255,255,255,0.1);
            padding: 10px;
            border-radius: 8px;
            word-break: break-word;
        }

        .remarks-box {
            background: rgba(255,255,255,0.05);
            padding: 15px;
            border-radius: 8px;
            margin-top: 10px;
            font-style: italic;
            border-left: 3px solid var(--primary);
        }

        .security-note {
            margin-top: 30px;
            padding: 15px;
            background: rgba(247, 37, 133, 0.1);
            border-radius: 8px;
            border-left: 3px solid var(--warning);
            font-size: 0.85rem;
        }

        .security-note i {
            color: var(--warning);
            margin-right: 8px;
        }

        /* Loading Spinner */
        .loading {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: white;
            text-align: center;
        }

        .loading i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: var(--primary);
        }

        .spinner {
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Disable interactions */
        .no-interaction {
            pointer-events: none;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .preview-container {
                flex-direction: column;
            }
            
            .info-panel {
                width: 100%;
                border-left: none;
                border-top: 1px solid rgba(255,255,255,0.1);
            }
            
            .pdf-controls {
                flex-wrap: wrap;
                width: 90%;
                border-radius: 12px;
                bottom: 20px;
            }
            
            .header-left {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="preview-header">
        <div class="header-left">
            <a href="dashboard.php" class="back-btn">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
            <div class="file-info">
                <i class="fas fa-<?= $is_pdf ? 'file-pdf' : 'file-image' ?>" style="color: <?= $is_pdf ? '#ff6b6b' : '#4cc9f0' ?>"></i>
                <div class="file-details">
                    <h2><?= htmlspecialchars($doc['file_name']) ?></h2>
                    <p>Uploaded by: <?= htmlspecialchars($doc['uploader_name']) ?> • <?= date('M d, Y h:i A', strtotime($doc['uploaded_at'])) ?></p>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Container -->
    <div class="preview-container">
        <!-- Watermark -->
        <div class="watermark">CONFIDENTIAL</div>
        
        <?php if ($is_pdf): ?>
        <!-- PDF Viewer -->
        <div class="pdf-viewer" id="pdfViewer">
            <div class="loading" id="loading">
                <i class="fas fa-spinner spinner"></i>
                <p>Loading PDF...</p>
            </div>
            <canvas id="pdfCanvas"></canvas>
            
            <!-- PDF Controls -->
            <div class="pdf-controls">
                <button onclick="prevPage()" id="prevBtn">
                    <i class="fas fa-chevron-left"></i> Previous
                </button>
                <span class="page-info" id="pageInfo">Page <span id="currentPage">1</span> of <span id="totalPages">1</span></span>
                <button onclick="nextPage()" id="nextBtn">
                    Next <i class="fas fa-chevron-right"></i>
                </button>
                <button onclick="zoomIn()">
                    <i class="fas fa-search-plus"></i> Zoom In
                </button>
                <button onclick="zoomOut()">
                    <i class="fas fa-search-minus"></i> Zoom Out
                </button>
                <button onclick="rotateDoc()">
                    <i class="fas fa-redo-alt"></i> Rotate
                </button>
            </div>
        </div>
        <?php elseif ($is_image): ?>
        <!-- Image Viewer -->
        <div class="image-viewer">
            <img src="serve_file.php?id=<?= $doc['id'] ?>" 
                 class="secure-image" 
                 alt="<?= htmlspecialchars($doc['file_name']) ?>"
                 oncontextmenu="return false;"
                 ondragstart="return false;">
        </div>
        <?php else: ?>
        <!-- Unsupported format -->
        <div class="image-viewer">
            <div style="text-align: center; color: white;">
                <i class="fas fa-exclamation-triangle" style="font-size: 4rem; color: var(--warning); margin-bottom: 1rem;"></i>
                <h2>Preview not available</h2>
                <p style="margin-top: 1rem; color: var(--gray);">This file type cannot be previewed.</p>
                <p style="font-size: 0.9rem;">File type: <?= strtoupper($file_ext) ?></p>
            </div>
        </div>
        <?php endif; ?>

        <!-- Info Panel -->
        <div class="info-panel">
            <h3><i class="fas fa-info-circle"></i> Document Information</h3>
            
            <div class="info-item">
                <div class="info-label">School</div>
                <div class="info-value">
                    <i class="fas fa-school" style="margin-right: 8px;"></i>
                    <?= htmlspecialchars($doc['school_name'] ?: 'N/A') ?>
                </div>
            </div>
            
            <div class="info-item">
                <div class="info-label">File Type</div>
                <div class="info-value">
                    <i class="fas fa-<?= $is_pdf ? 'file-pdf' : 'file' ?>" style="margin-right: 8px;"></i>
                    <?= strtoupper($file_ext) ?>
                </div>
            </div>
            
            <div class="info-item">
                <div class="info-label">File Size</div>
                <div class="info-value">
                    <i class="fas fa-weight-hanging" style="margin-right: 8px;"></i>
                    <?php
                    $size = $doc['file_size'];
                    if ($size >= 1073741824) {
                        echo number_format($size / 1073741824, 2) . ' GB';
                    } elseif ($size >= 1048576) {
                        echo number_format($size / 1048576, 2) . ' MB';
                    } elseif ($size >= 1024) {
                        echo number_format($size / 1024, 2) . ' KB';
                    } else {
                        echo $size . ' bytes';
                    }
                    ?>
                </div>
            </div>
            
            <div class="info-item">
                <div class="info-label">Uploaded By</div>
                <div class="info-value">
                    <i class="fas fa-user" style="margin-right: 8px;"></i>
                    <?= htmlspecialchars($doc['uploader_name']) ?>
                </div>
            </div>
            
            <div class="info-item">
                <div class="info-label">Upload Date</div>
                <div class="info-value">
                    <i class="fas fa-calendar-alt" style="margin-right: 8px;"></i>
                    <?= date('F d, Y', strtotime($doc['uploaded_at'])) ?><br>
                    <i class="fas fa-clock" style="margin-right: 8px;"></i>
                    <?= date('h:i A', strtotime($doc['uploaded_at'])) ?>
                </div>
            </div>
            
            <?php if (!empty($doc['remarks'])): ?>
            <div class="info-item">
                <div class="info-label">Remarks</div>
                <div class="remarks-box">
                    <i class="fas fa-quote-left" style="opacity: 0.5; margin-right: 5px;"></i>
                    <?= nl2br(htmlspecialchars($doc['remarks'])) ?>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="security-note">
                <i class="fas fa-lock"></i>
                <strong>Secure View Only</strong>
                <p style="margin-top: 10px; font-size: 0.8rem;">
                    This document is protected. Downloading, printing, and saving are disabled for security purposes.
                </p>
                <hr style="margin: 15px 0; border-color: rgba(255,255,255,0.1);">
                <p style="font-size: 0.8rem;">
                    <i class="fas fa-eye"></i> Previewed on: <?= date('F d, Y h:i A') ?>
                </p>
            </div>
        </div>
    </div>

    <?php if ($is_pdf): ?>
    <!-- PDF.js Library -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.16.105/pdf.min.js"></script>
    <script>
        
        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.16.105/pdf.worker.min.js';

        
        let pdfDoc = null;
        let pageNum = 1;
        let pageRendering = false;
        let pageNumPending = null;
        let scale = 1.5;
        let rotation = 0;
        const canvas = document.getElementById('pdfCanvas');
        const ctx = canvas.getContext('2d');

        
        function loadPDF() {
            const loadingTask = pdfjsLib.getDocument('serve_file.php?id=<?= $doc['id'] ?>');
            
            loadingTask.promise.then(function(pdf) {
                pdfDoc = pdf;
                document.getElementById('totalPages').textContent = pdf.numPages;
                document.getElementById('loading').style.display = 'none';
                
                
                renderPage(pageNum);
            }).catch(function(error) {
                console.error('Error loading PDF:', error);
                document.getElementById('loading').innerHTML = `
                    <i class="fas fa-exclamation-triangle" style="color: var(--warning);"></i>
                    <p>Error loading PDF. Please try again.</p>
                `;
            });
        }

        // Render page
        function renderPage(num) {
            pageRendering = true;
            
            pdfDoc.getPage(num).then(function(page) {
                const viewport = page.getViewport({ scale: scale, rotation: rotation });
                canvas.height = viewport.height;
                canvas.width = viewport.width;

                const renderContext = {
                    canvasContext: ctx,
                    viewport: viewport
                };

                const renderTask = page.render(renderContext);

                renderTask.promise.then(function() {
                    pageRendering = false;
                    if (pageNumPending !== null) {
                        renderPage(pageNumPending);
                        pageNumPending = null;
                    }
                });
            });

            document.getElementById('currentPage').textContent = num;
            updateButtons();
        }

        // Queue render page
        function queueRenderPage(num) {
            if (pageRendering) {
                pageNumPending = num;
            } else {
                renderPage(num);
            }
        }

        // Previous page
        function prevPage() {
            if (pageNum <= 1) return;
            pageNum--;
            queueRenderPage(pageNum);
        }

        // Next page
        function nextPage() {
            if (pageNum >= pdfDoc.numPages) return;
            pageNum++;
            queueRenderPage(pageNum);
        }

        // Zoom in
        function zoomIn() {
            scale += 0.25;
            queueRenderPage(pageNum);
        }

        // Zoom out
        function zoomOut() {
            if (scale > 0.5) {
                scale -= 0.25;
                queueRenderPage(pageNum);
            }
        }

        // Rotate
        function rotateDoc() {
            rotation = (rotation + 90) % 360;
            queueRenderPage(pageNum);
        }

        
        function updateButtons() {
            document.getElementById('prevBtn').disabled = pageNum <= 1;
            document.getElementById('nextBtn').disabled = pageNum >= pdfDoc.numPages;
        }

        
        document.addEventListener('keydown', function(e) {
            
            if ((e.ctrlKey && e.key === 's') || 
                (e.ctrlKey && e.key === 'p') ||
                (e.ctrlKey && e.shiftKey && e.key === 'I') ||
                e.key === 'F12' ||
                (e.ctrlKey && e.key === 'u')) {
                e.preventDefault();
                return false;
            }
            
            // Allow arrow keys for navigation
            if (e.key === 'ArrowLeft') {
                e.preventDefault();
                prevPage();
            }
            if (e.key === 'ArrowRight') {
                e.preventDefault();
                nextPage();
            }
        });

        
        document.addEventListener('contextmenu', function(e) {
            e.preventDefault();
            return false;
        });

       
        document.addEventListener('dragstart', function(e) {
            e.preventDefault();
            return false;
        });

        
        window.onload = loadPDF;
    </script>
    <?php endif; ?>

    <script>
        
        <?php if ($is_image): ?>
        
        document.querySelectorAll('.secure-image').forEach(img => {
            img.addEventListener('contextmenu', e => e.preventDefault());
            img.addEventListener('dragstart', e => e.preventDefault());
        });
        <?php endif; ?>

        
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.key === 'p') {
                e.preventDefault();
                alert('Printing is disabled for security.');
                return false;
            }
        });

        
        window.onbeforeunload = function() {
            return "Are you sure you want to leave?";
        };
    </script>
</body>
</html>