<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Foot Sizer</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body class="bg-gradient-to-br from-sacf_pink/20 via-purple-50 to-sacf_blue/20 min-h-screen flex items-center justify-center px-4 py-10">
    <div class="bg-white shadow-2xl rounded-3xl p-8 sm:p-10 w-full max-w-2xl border-t-4 border-sacf_blue">
        <div class="flex flex-col items-center mb-8">
            <div class="relative mb-4">
                <img src="{{ asset('logo/sac21.webp') }}" alt="Shoe A Child Foundation logo" class="h-24 w-auto">
                <div class="absolute -bottom-2 -right-2 bg-sacf_pink text-white rounded-full p-2 shadow-lg">
                    <i class="fas fa-shoe-prints text-xl"></i>
                </div>
            </div>
            <h2 class="text-4xl font-extrabold text-sacf_blue text-center mb-2">
                <i class="fas fa-ruler-vertical"></i> Foot Sizer
            </h2>
            <p class="text-gray-600 text-center text-sm">Measure your child's foot size accurately</p>
          </div>

        <form id="footForm" method="POST" action="{{ route('foot-sizer.process') }}" enctype="multipart/form-data" class="space-y-6">
            @csrf

            <div>
                <label for="name" class="block text-lg font-medium text-gray-800">
                    <i class="fas fa-user text-sacf_blue mr-2"></i>Child's Name
                </label>
                <input type="text" id="name" name="name" required placeholder="Enter child's full name"
                       class="mt-2 block w-full px-5 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-sacf_blue focus:border-sacf_blue text-gray-800 shadow-sm">
            </div>

            <div>
                <label for="age" class="block text-lg font-medium text-gray-800">
                    <i class="fas fa-birthday-cake text-sacf_pink mr-2"></i>Child's Age
                </label>
                <input type="number" id="age" name="age" required placeholder="Enter age in years"
                       class="mt-2 block w-full px-5 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-sacf_pink focus:border-sacf_pink text-gray-800 shadow-sm">
            </div>

            <div class="bg-gradient-to-r from-sacf_pink/10 to-purple-50 border-l-4 border-sacf_pink p-5 rounded-lg mb-6 shadow-sm">
                <h3 class="font-semibold text-lg mb-3 text-sacf_blue flex items-center">
                    <i class="fas fa-lightbulb mr-2 text-yellow-500"></i>Photo Tips for Accurate Measurement
                </h3>
                <ul class="space-y-2 text-sm text-gray-700">
                  <li class="flex items-start">
                      <i class="fas fa-check-circle text-green-500 mr-2 mt-1"></i>
                      <span>Place your foot flat on a white A4 paper.</span>
                  </li>
                  <li class="flex items-start">
                      <i class="fas fa-check-circle text-green-500 mr-2 mt-1"></i>
                      <span>Take the picture from directly above (90° angle), not at a slant.</span>
                  </li>
                  <li class="flex items-start">
                      <i class="fas fa-check-circle text-green-500 mr-2 mt-1"></i>
                      <span>Make sure the entire A4 sheet and your foot are clearly visible.</span>
                  </li>
                  <li class="flex items-start">
                      <i class="fas fa-check-circle text-green-500 mr-2 mt-1"></i>
                      <span>Ensure the lighting is good — avoid shadows.</span>
                  </li>
                </ul>
            </div>

            <div>
                <label for="photo" class="block text-lg font-medium text-gray-800">
                    <i class="fas fa-camera text-purple-600 mr-2"></i>Upload Foot Photo (with A4 Sheet)
                </label>
                <input type="file" id="photo" name="photo_path" accept="image/jpeg,image/png,image/webp" required
                       class="mt-2 block w-full text-sm text-gray-800 file:mr-4 file:py-3 file:px-6 file:rounded-xl file:border-0 file:text-sm file:font-semibold file:bg-sacf_blue file:text-white hover:file:bg-sacf_blue/90 shadow-sm transition">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-8">
                <button type="submit"
                        class="bg-sacf_blue text-white py-3 px-6 rounded-xl hover:bg-sacf_blue/90 hover:shadow-xl transition-all duration-200 font-semibold shadow-lg w-full transform hover:-translate-y-0.5">
                    <i class="fas fa-paper-plane mr-2"></i>Submit
                </button>

                <button type="button" id="clearButton"
                        class="bg-sacf_pink text-white py-3 px-6 rounded-xl hover:bg-sacf_pink/90 hover:shadow-xl transition-all duration-200 font-semibold shadow-lg w-full transform hover:-translate-y-0.5">
                    <i class="fas fa-eraser mr-2"></i>Clear Form
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
            resultDiv.innerHTML = `<p class="text-red-500 font-semibold"><i class="fas fa-exclamation-triangle mr-2"></i>Please upload a valid foot photo before submitting.</p>`;
            return;
        }

        resultDiv.innerHTML = '<p class="text-blue-600 font-medium animate-pulse"><i class="fas fa-spinner fa-spin mr-2"></i>Processing...</p>';

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
                    <div class="bg-gradient-to-r from-green-50 to-emerald-50 border-2 border-green-300 rounded-xl p-6 shadow-lg max-w-md mx-auto">
                        <h3 class="text-xl font-bold text-green-800 mb-4 flex items-center justify-center">
                            <i class="fas fa-check-circle mr-2 text-2xl"></i>Measurement Result
                        </h3>
                        <div class="space-y-2 text-gray-700">
                            <p class="flex items-center"><i class="fas fa-user text-sacf_blue mr-3 w-5"></i><strong>Name:</strong>&nbsp;${result.name}</p>
                            <p class="flex items-center"><i class="fas fa-birthday-cake text-sacf_pink mr-3 w-5"></i><strong>Age:</strong>&nbsp;${result.age}</p>
                            <p class="flex items-center"><i class="fas fa-ruler text-purple-600 mr-3 w-5"></i><strong>Foot Length:</strong>&nbsp;${result.foot_size_cm} cm</p>
                            <p class="flex items-center"><i class="fas fa-shoe-prints text-green-600 mr-3 w-5"></i><strong>Shoe Size:</strong>&nbsp;Size ${result.shoe_size}</p>
                        </div>
                    </div>
                `;
            } else {
                resultDiv.innerHTML = `<p class="text-red-500 font-semibold"><i class="fas fa-times-circle mr-2"></i>Failed to measure foot size. ${result?.message ?? 'Please try again.'}</p>`;
            }
        } catch (error) {
            console.error("Measurement error:", error);
            resultDiv.innerHTML = `<p class="text-red-500 font-semibold"><i class="fas fa-exclamation-circle mr-2"></i>An error occurred: ${error.message}</p>`;
        }
    });

    document.getElementById('clearButton').addEventListener('click', function () {
        document.getElementById('footForm').reset();
        document.getElementById('result').innerHTML = '';
    });
</script>

</body>
</html>
