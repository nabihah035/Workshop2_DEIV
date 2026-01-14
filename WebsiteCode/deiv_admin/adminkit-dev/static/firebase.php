<?php
// config/firebase.php

class FirebaseNotification {
    private $serverKey;
    private $webAppConfig;
    
    public function __construct() {
        // Your Firebase Web App Server Key (get from Firebase Console)
        $this->serverKey = ""; // Get from Firebase Console > Cloud Messaging
        
        $this->webAppConfig = [
            'apiKey' => "AIzaSyDpjUo6GfJEnxTwKBjnfzpkQruSzWvov-I",
            'authDomain' => "deiv-ac114.firebaseapp.com",
            'projectId' => "deiv-ac114",
            'storageBucket' => "deiv-ac114.firebasestorage.app",
            'messagingSenderId' => "846119732034",
            'appId' => "1:846119732034:web:213dfed9358f6d38773edb",
            'measurementId' => "G-V591C9RQJ4"
        ];
    }
    
    public function sendToTopic($topic, $title, $body, $data = []) {
        $url = 'https://fcm.googleapis.com/fcm/send';
        
        $notification = [
            'title' => $title,
            'body' => $body,
            'sound' => 'default',
            'badge' => '1'
        ];
        
        $fields = [
            'to' => '/topics/' . $topic,
            'notification' => $notification,
            'data' => $data,
            'priority' => 'high'
        ];
        
        $headers = [
            'Authorization: key=' . $this->serverKey,
            'Content-Type: application/json'
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));
        
        $result = curl_exec($ch);
        curl_close($ch);
        
        return json_decode($result, true);
    }
    
    public function sendToDevice($token, $title, $body, $data = []) {
        $url = 'https://fcm.googleapis.com/fcm/send';
        
        $notification = [
            'title' => $title,
            'body' => $body,
            'sound' => 'default',
            'badge' => '1'
        ];
        
        $fields = [
            'to' => $token,
            'notification' => $notification,
            'data' => $data,
            'priority' => 'high'
        ];
        
        $headers = [
            'Authorization: key=' . $this->serverKey,
            'Content-Type: application/json'
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));
        
        $result = curl_exec($ch);
        curl_close($ch);
        
        return json_decode($result, true);
    }
}
?>