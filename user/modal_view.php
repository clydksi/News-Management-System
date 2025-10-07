<?php foreach ($articles as $article): ?>
<div id="modal-<?= $article['id'] ?>" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden">
    <div class="bg-white rounded-lg w-11/12 md:w-1/2 p-6 relative">
        <button class="absolute top-2 right-2 text-gray-500 hover:text-gray-800" onclick="closeModal('modal-<?= $article['id'] ?>')">
            <span class="material-icons">close</span>
        </button>
        <h2 class="text-xl font-bold mb-4"><?= $article['title'] ?></h2>
        <p class="text-gray-700"><?= $article['content'] ?></p>
    </div>
</div>
<?php endforeach; ?>
