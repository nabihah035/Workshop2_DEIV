<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Kreait\Firebase\Factory;

$firebaseConfig = [
  apiKey: "AIzaSyDpjUo6GfJEnxTwKBjnfzpkQruSzWvov-I",
  authDomain: "deiv-ac114.firebaseapp.com",
  projectId: "deiv-ac114",
  storageBucket: "deiv-ac114.firebasestorage.app",
  messagingSenderId: "846119732034",
  appId: "1:846119732034:web:213dfed9358f6d38773edb",
  measurementId: "G-V591C9RQJ4"
];

// For Admin SDK (service account)
$serviceAccountPath = __DIR__ . '/service-account-key.json';

if (file_exists($serviceAccountPath)) {
    $factory = (new Factory)->withServiceAccount($serviceAccountPath);
    $firestore = $factory->createFirestore();
    $firebase_db = $firestore->database();
} else {
    // Fallback or error handling
    $firebase_db = null;
}