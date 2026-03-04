<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set header for JSON response
header('Content-Type: application/json');

// Configuration
$uploadDir = 'img/uploads/';
$dataFile = 'gallery.json';

// Create directories if they don't exist
if (!file_exists('img')) {
    mkdir('img', 0777, true);
}
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// Get the action from the request
$action = isset($_GET['action']) ? $_GET['action'] : '';

switch($action) {
    case 'upload':
        handleUpload();
        break;
    case 'get':
        getGallery();
        break;
    case 'delete':
        deleteImage();
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

function handleUpload() {
    global $uploadDir, $dataFile;
    
    // Check if file was uploaded
    if (!isset($_FILES['image'])) {
        echo json_encode(['success' => false, 'message' => 'No file uploaded']);
        return;
    }
    
    $file = $_FILES['image'];
    $caption = isset($_POST['caption']) ? $_POST['caption'] : '';
    $category = isset($_POST['category']) ? $_POST['category'] : 'general';
    
    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'File upload stopped by extension'
        ];
        $errorMsg = isset($errors[$file['error']]) ? $errors[$file['error']] : 'Unknown upload error';
        echo json_encode(['success' => false, 'message' => 'Upload error: ' . $errorMsg]);
        return;
    }
    
    // Check file size (max 5MB)
    if ($file['size'] > 5 * 1024 * 1024) {
        echo json_encode(['success' => false, 'message' => 'File too large. Max 5MB']);
        return;
    }
    
    // Check file type
    $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mimeType, $allowedTypes)) {
        echo json_encode(['success' => false, 'message' => 'Invalid file type. Only JPG, PNG, GIF, WEBP allowed']);
        return;
    }
    
    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid() . '_' . time() . '.' . $extension;
    $filepath = $uploadDir . $filename;
    
    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        // Create image data
        $imageData = [
            'id' => uniqid(),
            'filename' => $filename,
            'filepath' => $filepath,
            'caption' => $caption,
            'category' => $category,
            'date' => date('Y-m-d H:i:s')
        ];
        
        // Save to JSON file
        $gallery = [];
        if (file_exists($dataFile)) {
            $content = file_get_contents($dataFile);
            if (!empty($content)) {
                $gallery = json_decode($content, true);
                if (!is_array($gallery)) {
                    $gallery = [];
                }
            }
        }
        $gallery[] = $imageData;
        file_put_contents($dataFile, json_encode($gallery, JSON_PRETTY_PRINT));
        
        echo json_encode([
            'success' => true,
            'message' => 'Image uploaded successfully',
            'image' => $imageData
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to move uploaded file']);
    }
}

function getGallery() {
    global $dataFile;
    
    if (file_exists($dataFile)) {
        $content = file_get_contents($dataFile);
        if (!empty($content)) {
            $images = json_decode($content, true);
            if (is_array($images)) {
                echo json_encode(['success' => true, 'images' => $images]);
                return;
            }
        }
    }
    echo json_encode(['success' => true, 'images' => []]);
}

function deleteImage() {
    global $dataFile;
    
    $input = json_decode(file_get_contents('php://input'), true);
    $imageId = isset($input['id']) ? $input['id'] : '';
    $filepath = isset($input['filepath']) ? $input['filepath'] : '';
    
    if (!$imageId || !$filepath) {
        echo json_encode(['success' => false, 'message' => 'Missing image ID or path']);
        return;
    }
    
    // Delete physical file
    if (file_exists($filepath)) {
        unlink($filepath);
    }
    
    // Remove from JSON
    if (file_exists($dataFile)) {
        $content = file_get_contents($dataFile);
        if (!empty($content)) {
            $images = json_decode($content, true);
            if (is_array($images)) {
                $images = array_filter($images, function($img) use ($imageId) {
                    return $img['id'] !== $imageId;
                });
                file_put_contents($dataFile, json_encode(array_values($images), JSON_PRETTY_PRINT));
            }
        }
    }
    
    echo json_encode(['success' => true, 'message' => 'Image deleted successfully']);
}
?>
