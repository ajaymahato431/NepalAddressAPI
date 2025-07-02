<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nepal Address - API</title>
    <link rel="icon" type="image/png"
        href="https://notedinsights.com/wp-content/uploads/2024/01/android-chrome-512x512-1-75x75.png">
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100 font-sans">
    <!-- Header -->
    <header class="bg-blue-600 text-white py-6">
        <div class="container mx-auto px-4">
            <h1 class="text-4xl font-bold">Nepal Addresses API</h1>
            <p class="mt-2 text-lg">Access provinces, districts, and municipalities with our simple REST API</p>
        </div>
    </header>

    <!-- Main Content -->
    <main class="container mx-auto px-4 py-12">
        <section class="bg-white rounded-lg shadow-lg p-8">
            <h2 class="text-2xl font-semibold mb-6">API Endpoints</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Route 1 -->
                <div class="border rounded-lg p-6 bg-gray-50 hover:shadow-md transition">
                    <h3 class="text-xl font-medium text-blue-600">GET /provinces</h3>
                    <p class="mt-2 text-gray-600">Retrieve a list of all provinces.</p>
                    <code class="block mt-4 bg-gray-800 text-white p-4 rounded">
                        https://nepaladdress.notedinsights.com/api/provinces
                    </code>
                </div>

                <!-- Route 2 -->
                <div class="border rounded-lg p-6 bg-gray-50 hover:shadow-md transition">
                    <h3 class="text-xl font-medium text-blue-600">GET /districts</h3>
                    <p class="mt-2 text-gray-600">Retrieve a list of all districts.</p>
                    <code class="block mt-4 bg-gray-800 text-white p-4 rounded">
                        https://nepaladdress.notedinsights.com/api/districts
                    </code>
                </div>

                <!-- Route 3 -->
                <div class="border rounded-lg p-6 bg-gray-50 hover:shadow-md transition">
                    <h3 class="text-xl font-medium text-blue-600">GET /districts/{provinceName}</h3>
                    <p class="mt-2 text-gray-600">Retrieve districts filtered by a specific province name.</p>
                    <code class="block mt-4 bg-gray-800 text-white p-4 rounded">
                        https://nepaladdress.notedinsights.com/api/districts/{provinceName}
                    </code>
                </div>

                <!-- Route 4 -->
                <div class="border rounded-lg p-6 bg-gray-50 hover:shadow-md transition">
                    <h3 class="text-xl font-medium text-blue-600">GET /municipals/{districtName}</h3>
                    <p class="mt-2 text-gray-600">Retrieve municipalities filtered by a specific district name.</p>
                    <code class="block mt-4 bg-gray-800 text-white p-4 rounded">
                        https://nepaladdress.notedinsights.com/api/municipals/{districtName}
                    </code>
                </div>
            </div>
        </section>

        <!-- Usage Section -->
        <section class="mt-12">
            <h2 class="text-2xl font-semibold mb-6">How to Use</h2>
            <p class="text-gray-600">Send HTTP GET requests to the endpoints above to retrieve JSON data. Use the
                provided routes to access province, district, and municipal data efficiently. For example, to get
                districts for a specific province, replace <code>{provinceName}</code> with the desired province name in
                the URL.</p>
            <h2 class="text-2xl font-semibold mb-6 mt-6">Laravel Examples:</h2>
            <p class="text-gray-600">
                <a href="https://nepaladdress.notedinsights.com/api/provinces">$response =
                    Http::get('https://nepaladdress.notedinsights.com/api/provinces');</a><br />
                <a href="https://nepaladdress.notedinsights.com/api/districts">$response =
                    Http::get('https://nepaladdress.notedinsights.com/api/districts');</a><br />
                <a href="https://nepaladdress.notedinsights.com/api/districts/bagmati">$response =
                    Http::get('https://nepaladdress.notedinsights.com/api/districts/bagmati');</a><br />
                <a href="https://nepaladdress.notedinsights.com/api/municipals/chitwan">$response =
                    Http::get('https://nepaladdress.notedinsights.com/api/municipals/chitwan');</a>

            </p>
        </section>
    </main>

    <!-- Footer -->
    <footer class="bg-gray-800 text-white py-6">
        <div class="container mx-auto px-4 text-center">
            <p>&copy; {{ date('Y') }} <a href="https://notedinsights.com">Noted Insights</a>. All rights reserved.
            </p>
        </div>
    </footer>
</body>

</html>
