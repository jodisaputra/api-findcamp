<?php

return [
    'project_id' => env('FIREBASE_PROJECT_ID', 'findcamp-926de'),
    'credentials' => [
        'file' => storage_path('app/firebase/firebase-credentials.json'),
    ],
    'database_url' => env('FIREBASE_DATABASE_URL', 'https://findcamp-926de-default-rtdb.asia-southeast1.firebasedatabase.app'),
];
