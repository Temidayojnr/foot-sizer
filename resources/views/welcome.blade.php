<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Foot Sizer</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gradient-to-tr from-sacf_pink/10 via-white to-sacf_blue/10 min-h-screen flex items-center justify-center px-4 py-10">
    <div class="bg-white shadow-2xl rounded-2xl p-10 w-full max-w-2xl">
        <div class="flex flex-col items-center mb-6">
            <img src="{{ asset('logo/sac21.webp') }}" alt="Shoe A Child Foundation logo" class="h-20 w-auto mb-4">
            <h2 class="text-3xl font-extrabold text-sacf_blue text-center">👣 Foot Sizer</h2>
          </div>

        <form id="footForm" method="POST" action="{{ route('foot-sizer.process') }}" enctype="multipart/form-data" class="space-y-6">
            @csrf

            <div>
                <label for="name" class="block text-lg font-medium text-gray-800">Child's Name</label>
                <input type="text" id="name" name="name" required
                       class="mt-2 block w-full px-5 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-blue-400 text-gray-800 shadow-sm">
            </div>

            <div>
                <label for="age" class="block text-lg font-medium text-gray-800">Child's Age</label>
                <input type="number" id="age" name="age" required
                       class="mt-2 block w-full px-5 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-blue-400 text-gray-800 shadow-sm">
            </div>

            <div class="bg-sacf_pink/10 border-l-4 border-sacf_pink text-sacf_pink p-4 rounded mb-6">
                <h3 class="font-semibold text-lg mb-2">Photo Tips for Accurate Measurement</h3>
                <ul class="list-disc list-inside space-y-1 text-sm">
                  <li>Place your foot flat on a white A4 paper.</li>
                  <li>Take the picture from directly above (90° angle), not at a slant.</li>
                  <li>Make sure the entire A4 sheet and your foot are clearly visible.</li>
                  <li>Ensure the lighting is good — avoid shadows.</li>
                </ul>
            </div>

            <div>
                <label for="photo" class="block text-lg font-medium text-gray-800">Upload Foot Photo (with A4 Sheet)</label>
                <input type="file" id="photo" name="photo_path" accept="image/jpeg,image/png,image/webp" required
                       class="mt-2 block w-full text-sm text-gray-800 file:mr-4 file:py-3 file:px-6 file:rounded-xl file:border-0 file:text-sm file:font-semibold file:bg-blue-600 file:text-white hover:file:bg-blue-700 shadow-sm">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-6">
                <button type="submit"
                        class="bg-sacf_blue text-white py-3 px-6 rounded-xl hover:bg-sacf_blue/90 transition duration-200 font-semibold shadow-lg w-full">
                 Submit
                </button>

                <button type="button" id="clearButton"
                        class="bg-sacf_pink text-white py-3 px-6 rounded-xl hover:bg-sacf_pink/90 transition duration-200 font-semibold shadow-lg w-full">
                  Clear Form
                </button>
            </div>
        </form>

        <div id="result" class="mt-10 text-center text-base text-gray-800"></div>
    </div>

    <script src="https://cdn.tailwindcss.com"></script>
    <script>
    tailwind.config = {
        theme: {
        extend: {
            colors: {
            sacf_blue: '#002F6C',
            sacf_pink: '#FF40C8',
            }
        }
        }
    }
    </script>

    {{-- <script>
        document.getElementById('footForm').addEventListener('submit', async function (e) {
            e.preventDefault();

            const form = e.target;
            const formData = new FormData(form);
            const resultDiv = document.getElementById('result');
            resultDiv.innerHTML = '<p class="text-blue-600 font-medium animate-pulse">Processing...</p>';

            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    body: formData,
                });

                const result = await response.json();

                if (result.foot_size_cm) {
                    resultDiv.innerHTML = `
                        <div class="bg-green-50 border border-green-200 rounded-xl p-6 shadow-md max-w-md mx-auto">
                            <h3 class="text-lg font-bold text-green-800 mb-2">✅ Measurement Result</h3>
                            <p><strong>Name:</strong> ${result.name}</p>
                            <p><strong>Age:</strong> ${result.age}</p>
                            <p><strong>Foot Length:</strong> ${result.foot_size_cm} cm</p>
                            <p><strong>Shoe Size:</strong> Size ${result.shoe_size}</p>
                            <img src="${result.photo_url}" alt="Uploaded Foot Image" class="mt-4 mx-auto rounded-xl shadow-md max-w-full">
                        </div>
                    `;
                } else {
                    resultDiv.innerHTML = `<p class="text-red-500 font-semibold">❌ Failed to measure foot size. Please try again.</p>`;
                }
            } catch (error) {
                resultDiv.innerHTML = `<p class="text-red-500 font-semibold">⚠️ Error: ${error.message}</p>`;
            }
        });

        document.getElementById('clearButton').addEventListener('click', function () {
            document.getElementById('footForm').reset();
            document.getElementById('result').innerHTML = '';
        });
    </script> --}}

    <script>
    document.getElementById('footForm').addEventListener('submit', async function (e) {
        e.preventDefault();

        const form = e.target;
        const formData = new FormData(form);
        const resultDiv = document.getElementById('result');
        const file = formData.get('photo_path');

        // Validate file input
        if (!file || file.size === 0) {
            resultDiv.innerHTML = `<p class="text-red-500 font-semibold">⚠️ Please upload a valid foot photo before submitting.</p>`;
            return;
        }

        resultDiv.innerHTML = '<p class="text-blue-600 font-medium animate-pulse">Processing...</p>';

        try {
            const response = await fetch(form.action || "{{ route('foot-sizer.process') }}", {
                method: 'POST',
                body: formData,
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                }
            });

            const result = await response.json();

            if (response.ok && result.foot_size_cm) {
                resultDiv.innerHTML = `
                    <div class="bg-green-50 border border-green-200 rounded-xl p-6 shadow-md max-w-md mx-auto">
                        <h3 class="text-lg font-bold text-green-800 mb-2">✅ Measurement Result</h3>
                        <p><strong>Name:</strong> ${result.name}</p>
                        <p><strong>Age:</strong> ${result.age}</p>
                        <p><strong>Foot Length:</strong> ${result.foot_size_cm} cm</p>
                        <p><strong>Shoe Size:</strong> Size ${result.shoe_size}</p>
                    </div>
                `;
            } else {
                resultDiv.innerHTML = `<p class="text-red-500 font-semibold">❌ Failed to measure foot size. ${result?.message ?? 'Please try again.'}</p>`;
            }
        } catch (error) {
            console.error("Measurement error:", error);
            resultDiv.innerHTML = `<p class="text-red-500 font-semibold">⚠️ An error occurred: ${error.message}</p>`;
        }
    });

    document.getElementById('clearButton').addEventListener('click', function () {
        document.getElementById('footForm').reset();
        document.getElementById('result').innerHTML = '';
    });
</script>

</body>
</html>
