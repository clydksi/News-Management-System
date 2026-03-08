<?php
require '../auth.php';
require '../db.php';
require '../csrf.php';

// Fetch categories
$catStmt = $pdo->query("SELECT * FROM categories ORDER BY name ASC");
$categories = $catStmt->fetchAll(PDO::FETCH_ASSOC);

$uploadError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $title    = $_POST['title'];
    $content  = $_POST['content'];
    $category = $_POST['category'];
    $user = $_SESSION['user_id'];
    $dept = $_SESSION['department_id'];

    $fileName = null;
    if (!empty($_FILES['attachment']['name'])) {
        $allowedMimes = [
            'image/jpeg', 'image/png', 'image/gif',
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'text/plain',
        ];
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($_FILES['attachment']['tmp_name']);

        if (!in_array($mime, $allowedMimes, true)) {
            $uploadError = "Invalid file type. Allowed: JPG, PNG, GIF, PDF, DOC, DOCX, TXT.";
        } else {
            $uploadDir  = __DIR__ . "/uploads/";
            $fileName   = time() . "_" . basename($_FILES['attachment']['name']);
            $targetFile = $uploadDir . $fileName;
            if (!move_uploaded_file($_FILES['attachment']['tmp_name'], $targetFile)) {
                $fileName = null;
            }
        }
    }

    if (!$uploadError) {
        $stmt = $pdo->prepare("INSERT INTO news (title, content, category_id, department_id, created_by, attachment)
                               VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$title, $content, $category, $dept, $user, $fileName]);

        header("Location: user_dashboard.php");
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Create News - Share Your Story</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gradient-to-br from-indigo-500 to-purple-600 min-h-screen p-4">
  <div class="max-w-3xl mx-auto animate-fadeIn">

    <!-- Header -->
    <div class="bg-white/95 backdrop-blur rounded-t-2xl p-6 shadow-md flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
      <h2 class="text-2xl font-semibold text-gray-800 flex items-center gap-2">
        📰 Create News Article
      </h2>
      <a href="user_dashboard.php" class="px-4 py-2 bg-gradient-to-r from-indigo-500 to-purple-600 text-white rounded-lg font-semibold shadow hover:shadow-lg transition">
        ⬅️ Back to Dashboard
      </a>
    </div>

    <!-- Form Container -->
    <div class="bg-white/95 backdrop-blur rounded-b-2xl p-6 shadow-lg">

      <!-- Tips -->
      <div class="bg-indigo-50 border border-indigo-200 rounded-xl p-4 mb-6 text-indigo-700">
        <h4 class="font-semibold mb-2">✨ Writing Tips:</h4>
        <ul class="list-disc list-inside text-sm space-y-1">
          <li>Write a clear, engaging headline that summarizes your news</li>
          <li>Include the most important information in the first paragraph</li>
          <li>Keep your content concise and easy to read</li>
          <li>Add attachments to support your story (images, documents)</li>
        </ul>
      </div>

      <?php if ($uploadError): ?>
        <div class="bg-red-100 border border-red-300 text-red-700 rounded-xl p-3 mb-4">
          <?= htmlspecialchars($uploadError) ?>
        </div>
      <?php endif; ?>

      <form method="post" enctype="multipart/form-data" id="newsForm" class="space-y-6">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
        <!-- Title -->
        <div>
          <label for="title" class="block font-semibold mb-1">📝 Article Title</label>
          <input type="text" name="title" id="title" required maxlength="200"
            placeholder="Enter a compelling headline..."
            class="w-full p-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-indigo-500">
          <p id="titleCounter" class="text-xs text-gray-500 mt-1">0/200</p>
        </div>

        <!-- Content -->
        <div>
          <label for="content" class="block font-semibold mb-1">📄 Article Content</label>
          <textarea name="content" id="content" required placeholder="Share your news story here..."
            class="w-full p-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-indigo-500 min-h-[150px]"></textarea>
          <p id="contentCounter" class="text-xs text-gray-500 mt-1">0 characters</p>
        </div>

        <!-- Category -->
        <div>
          <label for="category" class="block font-semibold mb-1">📂 Category</label>
          <select name="category" id="category" required
            class="w-full p-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-indigo-500">
            <option value="">-- Select a Category --</option>
            <?php foreach ($categories as $cat): ?>
              <option value="<?= htmlspecialchars($cat['id']) ?>">
                <?= htmlspecialchars($cat['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- Preview -->
        <button type="button" id="togglePreview" class="text-indigo-600 underline text-sm">👀 Preview Article</button>
        <div id="previewSection" class="hidden border border-gray-200 rounded-lg p-4 bg-gray-50">
          <h3 id="previewTitle" class="font-semibold text-lg mb-2 text-gray-800">Your Title Will Appear Here</h3>
          <p id="previewContent" class="text-gray-600 whitespace-pre-wrap">Your content will appear here...</p>
        </div>

        <!-- File Upload -->
        <div>
          <label for="attachment" class="block font-semibold mb-1">📎 Attachment (Optional)</label>
          <input type="file" name="attachment" id="attachment"
            class="block w-full text-sm text-gray-600 border-2 border-dashed border-gray-300 rounded-lg cursor-pointer p-3"
            accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx,.txt">
          <p id="fileInfo" class="hidden text-sm text-gray-500 mt-2">Selected: <span id="fileName"></span> <span id="fileSize"></span></p>
        </div>

        <!-- Submit -->
        <button type="submit" id="submitBtn"
          class="w-full py-3 bg-gradient-to-r from-indigo-500 to-purple-600 text-white rounded-lg font-semibold shadow hover:shadow-lg transition">
          🚀 Publish Article
        </button>
      </form>
    </div>
  </div>

  <script>
    // JS logic same as your original (counters, preview, file info...)
    const titleInput = document.getElementById('title');
    const contentInput = document.getElementById('content');
    const titleCounter = document.getElementById('titleCounter');
    const contentCounter = document.getElementById('contentCounter');
    const togglePreview = document.getElementById('togglePreview');
    const previewSection = document.getElementById('previewSection');
    const previewTitle = document.getElementById('previewTitle');
    const previewContent = document.getElementById('previewContent');
    const fileInput = document.getElementById('attachment');
    const fileInfo = document.getElementById('fileInfo');
    const fileName = document.getElementById('fileName');
    const fileSize = document.getElementById('fileSize');
    let previewVisible = false;

    titleInput.addEventListener('input', () => {
      titleCounter.textContent = `${titleInput.value.length}/200`;
      titleCounter.classList.toggle('text-red-500', titleInput.value.length > 180);
      if (previewVisible) previewTitle.textContent = titleInput.value || 'Your Title Will Appear Here';
    });

    contentInput.addEventListener('input', () => {
      contentCounter.textContent = `${contentInput.value.length} characters`;
      if (previewVisible) previewContent.textContent = contentInput.value || 'Your content will appear here...';
    });

    togglePreview.addEventListener('click', () => {
      previewVisible = !previewVisible;
      previewSection.classList.toggle('hidden', !previewVisible);
      togglePreview.textContent = previewVisible ? '🙈 Hide Preview' : '👀 Preview Article';
      previewTitle.textContent = titleInput.value || 'Your Title Will Appear Here';
      previewContent.textContent = contentInput.value || 'Your content will appear here...';
    });

    fileInput.addEventListener('change', () => {
      const file = fileInput.files[0];
      if (file) {
        fileInfo.classList.remove('hidden');
        fileName.textContent = file.name;
        fileSize.textContent = `(${(file.size / 1024).toFixed(1)} KB)`;
      } else {
        fileInfo.classList.add('hidden');
      }
    });
  </script>
</body>
</html>
