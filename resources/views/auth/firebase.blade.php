<!DOCTYPE html>
<html>

<head>
    <title>Firebase Authentication</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
</head>

<body>
    <h2>Firebase Authentication</h2>
    <button id="googleSignIn">Sign in with Google</button>
    <div id="result" style="margin-top: 20px;"></div>

    <script type="module">
        import {
            initializeApp
        } from "https://www.gstatic.com/firebasejs/11.3.0/firebase-app.js";
        import {
            getAuth,
            signInWithPopup,
            GoogleAuthProvider
        } from "https://www.gstatic.com/firebasejs/11.3.0/firebase-auth.js";

        const firebaseConfig = {
            apiKey: "AIzaSyAnEDNmD4idvJzitez2qfBm0ebbGRHChxo",
            authDomain: "findcamp-926de.firebaseapp.com",
            databaseURL: "https://findcamp-926de-default-rtdb.asia-southeast1.firebasedatabase.app",
            projectId: "findcamp-926de",
            storageBucket: "findcamp-926de.firebasestorage.app",
            messagingSenderId: "1035961176435",
            appId: "1:1035961176435:web:e8b9d9e8b4194e44fe3506"
        };

        // Initialize Firebase
        const app = initializeApp(firebaseConfig);
        const auth = getAuth(app);
        const provider = new GoogleAuthProvider();

        // Add click event listener
        document.getElementById('googleSignIn').addEventListener('click', async function() {
            try {
                // Show loading state
                document.getElementById('result').innerHTML = 'Signing in...';

                const result = await signInWithPopup(auth, provider);
                const user = result.user;
                const idToken = await user.getIdToken();

                console.log('Got ID Token:', idToken);

                // Send to Laravel API
                const response = await fetch('/api/auth/login', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json', // Add this line
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({
                        idToken: idToken,
                        email: user.email, // Add these fields
                        name: user.displayName
                    })
                });

                // Check if response is ok
                if (!response.ok) {
                    const contentType = response.headers.get('content-type');
                    if (contentType && contentType.includes('application/json')) {
                        const errorData = await response.json();
                        throw new Error(errorData.error || 'API request failed');
                    } else {
                        const text = await response.text();
                        console.error('Raw response:', text); // Log raw response for debugging
                        throw new Error('Invalid server response');
                    }
                }

                const data = await response.json();
                console.log('API Response:', data); // Log the response

                document.getElementById('result').innerHTML =
                    `Login successful!<br>User: ${data.user.email}<br>Token received and stored.`;

                // Store token
                localStorage.setItem('access_token', data.access_token);

            } catch (error) {
                console.error('Full error:', error); // Detailed error logging
                document.getElementById('result').innerHTML =
                    `Error: ${error.message}<br>Please check console for details.`;
            }
        });
    </script>
</body>

</html>
