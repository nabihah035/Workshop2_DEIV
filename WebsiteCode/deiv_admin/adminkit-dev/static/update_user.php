<?php
// update_user.php
session_start();
require_once dirname(dirname(__DIR__)) . '/config/db.php';

header('Content-Type: application/json');

try {
    // Get form data
    $user_id = $_POST['id'] ?? '';
    $first_name = $_POST['first_name'] ?? '';
    $last_name = $_POST['last_name'] ?? '';
    $email = $_POST['email'] ?? '';
    $organization = $_POST['organization'] ?? '';
    $role = $_POST['role'] ?? '';
    $status = $_POST['status'] ?? '';
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $sync_firebase = $_POST['sync_firebase'] ?? false;
    
    if (empty($user_id) || empty($email)) {
        throw new Exception('User ID and Email are required');
    }
    
    // 1. Update MySQL (WITHOUT updated_at column)
    if (!empty($password)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        $stmt = $pdo->prepare("UPDATE user SET 
            first_name = ?, 
            last_name = ?, 
            email = ?, 
            username = ?,
            organization = ?, 
            role = ?, 
            status = ?, 
            password = ?
            WHERE User_id = ?");
        $success = $stmt->execute([$first_name, $last_name, $email, $username, $organization, $role, $status, $hashed_password, $user_id]);
        
    } else {
        $stmt = $pdo->prepare("UPDATE user SET 
            first_name = ?, 
            last_name = ?, 
            email = ?, 
            username = ?,
            organization = ?, 
            role = ?, 
            status = ?
            WHERE User_id = ?");
        $success = $stmt->execute([$first_name, $last_name, $email, $username, $organization, $role, $status, $user_id]);
    }
    
    if (!$success) {
        // Try alternative ID field (some systems use 'id' instead of 'User_id')
        if (!empty($password)) {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            $stmt = $pdo->prepare("UPDATE user SET 
                first_name = ?, 
                last_name = ?, 
                email = ?, 
                username = ?,
                organization = ?, 
                role = ?, 
                status = ?, 
                password = ?
                WHERE id = ?");
            $success = $stmt->execute([$first_name, $last_name, $email, $username, $organization, $role, $status, $hashed_password, $user_id]);
            
        } else {
            $stmt = $pdo->prepare("UPDATE user SET 
                first_name = ?, 
                last_name = ?, 
                email = ?, 
                username = ?,
                organization = ?, 
                role = ?, 
                status = ?
                WHERE id = ?");
            $success = $stmt->execute([$first_name, $last_name, $email, $username, $organization, $role, $status, $user_id]);
        }
        
        if (!$success) {
            throw new Exception('User not found or no changes made');
        }
    }
    
    $firestore_updated = false;
    $firestore_error = null;
    
    // 2. Update Firebase if requested
    if ($sync_firebase) {
        try {
            // Use Firebase REST API
            $firebaseProjectId = 'deiv-ac114';
            $apiKey = 'AIzaSyDpjUo6GfJEnxTwKBjnfzpkQruSzWvov-I';
            $firebaseUrl = "https://firestore.googleapis.com/v1/projects/{$firebaseProjectId}/databases/(default)/documents/users/{$user_id}?key={$apiKey}";
            
            // Prepare data for Firebase
            $firebaseData = [
                'fields' => [
                    'first_name' => ['stringValue' => $first_name],
                    'last_name' => ['stringValue' => $last_name],
                    'email' => ['stringValue' => $email],
                    'username' => ['stringValue' => $username],
                    'organization' => ['stringValue' => $organization],
                    'role' => ['stringValue' => $role],
                    'status' => ['stringValue' => strtolower($status)],
                    'mysql_synced_at' => ['stringValue' => date('Y-m-d H:i:s')]
                ]
            ];
            
            // Make PATCH request to update document
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $firebaseUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($firebaseData));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Accept: application/json'
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode == 200) {
                $firestore_updated = true;
                error_log("✅ Firebase updated for user: $user_id");
            } else {
                // Try to create document if it doesn't exist
                if ($httpCode == 404) {
                    $createUrl = "https://firestore.googleapis.com/v1/projects/{$firebaseProjectId}/databases/(default)/documents/users?documentId={$user_id}&key={$apiKey}";
                    
                    $createData = [
                        'fields' => $firebaseData['fields']
                    ];
                    
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $createUrl);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                    curl_setopt($ch, CURLOPT_POST, 1);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($createData));
                    curl_setopt($ch, CURLOPT_HTTPHEADER, [
                        'Content-Type: application/json',
                        'Accept: application/json'
                    ]);
                    
                    $response = curl_exec($ch);
                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);
                    
                    if ($httpCode == 200) {
                        $firestore_updated = true;
                        error_log("✅ Firebase document created for user: $user_id");
                    } else {
                        $responseData = json_decode($response, true);
                        $firestore_error = "Failed to create Firebase document. HTTP Code: $httpCode";
                        if (isset($responseData['error']['message'])) {
                            $firestore_error .= " - " . $responseData['error']['message'];
                        }
                        error_log("❌ Firebase creation failed for user $user_id. HTTP Code: $httpCode, Response: $response");
                    }
                } else {
                    $responseData = json_decode($response, true);
                    $firestore_error = "Firebase update failed. HTTP Code: $httpCode";
                    if (isset($responseData['error']['message'])) {
                        $firestore_error .= " - " . $responseData['error']['message'];
                    }
                    error_log("❌ Firebase update failed for user $user_id. HTTP Code: $httpCode, Response: $response");
                }
            }
            
        } catch (Exception $firebaseError) {
            error_log("❌ Firebase update failed for user $user_id: " . $firebaseError->getMessage());
            $firestore_error = $firebaseError->getMessage();
        }
    }
    
    $_SESSION['success'] = 'User updated successfully' . ($firestore_updated ? ' (Firebase synced)' : ' (MySQL only)');
    
    echo json_encode([
        'success' => true,
        'message' => 'User updated successfully',
        'firestore_updated' => $firestore_updated,
        'firestore_error' => $firestore_error,
        'mysql_updated' => true
    ]);
    
} catch (Exception $e) {
    error_log("❌ Update error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>