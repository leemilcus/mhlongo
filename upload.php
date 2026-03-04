<?php
header('Content-Type: application/json');

// Configuration
$uploadDir = 'img/uploads/';
$allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
$maxFileSize = 5 * 1024 * 1024; // 5MB

// Create upload directory if it doesn't exist
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// Get JSON data
$data = json_decode(file_get_contents('php://input'), true);

// Handle different actions
$action = $_GET['action'] ?? '';

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
    global $uploadDir, $allowedTypes, $maxFileSize;
    
    // Check if file was uploaded
    if (!isset($_FILES['image'])) {
        echo json_encode(['success' => false, 'message' => 'No file uploaded']);
        return;
    }
    
    $file = $_FILES['image'];
    $caption = $_POST['caption'] ?? '';
    $category = $_POST['category'] ?? 'general';
    
    // Check for errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'Upload error: ' . $file['error']]);
        return;
    }
    
    // Check file size
    if ($file['size'] > $maxFileSize) {
        echo json_encode(['success' => false, 'message' => 'File too large. Max 5MB']);
        return;
    }
    
    // Check file type
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
        // Save image info to JSON file
        $imageData = [
            'id' => uniqid(),
            'filename' => $filename,
            'filepath' => $filepath,
            'caption' => $caption,
            'category' => $category,
            'date' => date('Y-m-d H:i:s')
        ];
        
        saveImageInfo($imageData);
        
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
    $galleryFile = 'gallery.json';
    
    if (file_exists($galleryFile)) {
        $data = json_decode(file_get_contents($galleryFile), true);
        echo json_encode(['success' => true, 'images' => $data]);
    } else {
        echo json_encode(['success' => true, 'images' => []]);
    }
}

function deleteImage() {
    $data = json_decode(file_get_contents('php://input'), true);
    $imageId = $data['id'] ?? '';
    $filepath = $data['filepath'] ?? '';
    
    if (!$imageId || !$filepath) {
        echo json_encode(['success' => false, 'message' => 'Missing image ID or path']);
        return;
    }
    
    // Delete physical file
    if (file_exists($filepath)) {
        unlink($filepath);
    }
    
    // Remove from JSON
    $galleryFile = 'gallery.json';
    if (file_exists($galleryFile)) {
        $images = json_decode(file_get_contents($galleryFile), true);
        $images = array_filter($images, function($img) use ($imageId) {
            return $img['id'] !== $imageId;
        });
        file_put_contents($galleryFile, json_encode(array_values($images), JSON_PRETTY_PRINT));
    }
    
    echo json_encode(['success' => true, 'message' => 'Image deleted successfully']);
}

function saveImageInfo($imageData) {
    $galleryFile = 'gallery.json';
    
    if (file_exists($galleryFile)) {
        $images = json_decode(file_get_contents($galleryFile), true);
        $images[] = $imageData;
    } else {
        $images = [$imageData];
    }
    
    file_put_contents($galleryFile, json_encode($images, JSON_PRETTY_PRINT));
}
?>
