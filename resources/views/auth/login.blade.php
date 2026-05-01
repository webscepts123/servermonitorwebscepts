<!DOCTYPE html>
<html>
<head>
    <title>Login</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-900 flex items-center justify-center h-screen">

<div class="bg-white p-8 rounded shadow w-96">
    <h2 class="text-2xl font-bold mb-6 text-center">Login</h2>

    @if(session('error'))
        <div class="bg-red-200 p-2 mb-3">{{ session('error') }}</div>
    @endif

    <form method="POST" action="/login">
        @csrf

        <input type="email" name="email" placeholder="Email"
               class="w-full p-2 border mb-3" required>

        <input type="password" name="password" placeholder="Password"
               class="w-full p-2 border mb-3" required>

        <button class="w-full bg-black text-white p-2">Login</button>
    </form>
</div>

</body>
</html>