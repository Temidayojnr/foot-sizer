<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Foot Sizer</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gradient-to-tr from-blue-100 via-white to-blue-100 min-h-screen flex items-center justify-center px-4 py-10">
    <div class="bg-white shadow-2xl rounded-2xl p-10 w-full max-w-2xl">
        <h2 class="text-3xl font-extrabold text-blue-800 text-center mb-8">👣 Shoe A Child Foundation Foot Sizer</h2>

        <form id="footForm" method="POST" enctype="multipart/form-data" action="{{ route('foot-sizer.process') }}" class="space-y-6">
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

            <div class="bg-blue-50 border-l-4 border-blue-500 text-blue-700 p-4 rounded mb-6">
                <h3 class="font-semibold text-lg mb-2">📸 Photo Tips for Accurate Measurement</h3>
                <ul class="list-disc list-inside space-y-1 text-sm">
                  <li>Place your foot **flat on a white A4 paper**.</li>
                  <li>Take the picture from **directly above** (90° angle), not at a slant.</li>
                  <li>Make sure the **entire A4 sheet and your foot** are clearly visible.</li>
                  <li>Ensure the lighting is good — avoid shadows.</li>
                </ul>
              </div>

            <div>
                <label for="photo" class="block text-lg font-medium text-gray-800">Upload Foot Photo (with A4 Sheet)</label>
                <input type="file" id="photo" name="photo_path" accept="image/*" required
                       class="mt-2 block w-full text-sm text-gray-800 file:mr-4 file:py-3 file:px-6 file:rounded-xl file:border-0 file:text-sm file:font-semibold file:bg-blue-600 file:text-white hover:file:bg-blue-700 shadow-sm">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-6">
                <button type="submit"
                        class="bg-blue-600 text-white py-3 px-6 rounded-xl hover:bg-blue-700 transition duration-200 font-semibold shadow-lg w-full">
                    🧮 Submit
                </button>

                <button type="button" id="clearButton"
                        class="bg-gray-300 text-gray-800 py-3 px-6 rounded-xl hover:bg-gray-400 transition duration-200 font-medium shadow-md w-full">
                    🧹 Clear Form
                </button>
            </div>
        </form>

        <div id="result" class="mt-10 text-center text-base text-gray-800"></div>
    </div>

    <script>
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
    </script>
</body>
</html>
