<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Foot Sizer App Logs</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body class="bg-gray-900 text-gray-100 font-mono p-4 sm:p-6">
    <div class="max-w-6xl mx-auto">
        <h1 class="text-xl sm:text-2xl font-bold mb-4 sm:mb-6 text-green-400 text-center">
            <i class="fas fa-file-alt mr-2"></i>Foot Sizer Logs
        </h1>

        @foreach($logs as $log)
            @php
                $levelColor = match($log['level']) {
                    'ERROR' => 'text-red-400',
                    'WARNING' => 'text-yellow-400',
                    'DEBUG' => 'text-blue-400',
                    default => 'text-green-400',
                };
            @endphp

            <div class="bg-gray-800 rounded-md p-3 sm:p-4 mb-4 shadow-md border border-gray-700">
                <div class="text-xs sm:text-sm {{ $levelColor }} font-bold break-all">
                    @if($log['level'] === 'ERROR')
                        <i class="fas fa-times-circle mr-2"></i>
                    @elseif($log['level'] === 'WARNING')
                        <i class="fas fa-exclamation-triangle mr-2"></i>
                    @elseif($log['level'] === 'DEBUG')
                        <i class="fas fa-bug mr-2"></i>
                    @else
                        <i class="fas fa-info-circle mr-2"></i>
                    @endif
                    [{{ $log['timestamp'] }}] {{ $log['level'] }}
                </div>
                <div class="mt-2 overflow-x-auto">
                    <pre class="whitespace-pre-wrap text-xs sm:text-sm text-gray-300 break-words">{{ $log['message'] }}</pre>
                </div>
            </div>
        @endforeach

        <div class="mt-6 sm:mt-8">
            {{ $logs->appends(['password' => request('password')])->links('pagination::tailwind') }}
        </div>
    </div>
</body>
</html>
