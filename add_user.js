document.addEventListener("DOMContentLoaded", () => {

  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
  const addUserModal = document.getElementById('addUserModal');
  const addUserBtn = document.getElementById('addUserBtn'); // button that opens modal
  const closeAddUserModal = document.getElementById('closeAddUserModal');
  const cancelAddUser = document.getElementById('cancelAddUser');
  const addUserForm = document.getElementById('addUserForm');

  // Show / hide modal
  const showAddModal = () => addUserModal.classList.replace('hidden', 'flex');
  const hideAddModal = () => addUserModal.classList.replace('flex', 'hidden');

  addUserBtn?.addEventListener('click', showAddModal);
  [closeAddUserModal, cancelAddUser].forEach(btn => btn?.addEventListener('click', hideAddModal));
  addUserModal.addEventListener('click', e => { if (e.target === addUserModal) hideAddModal(); });

  // Clear previous highlights
  const clearHighlights = () => {
    addUserForm.querySelectorAll('input, select').forEach(el => {
      el.classList.remove('border-red-500', 'bg-red-50');
    });
  };

  addUserForm.addEventListener('submit', e => {
    e.preventDefault();
    clearHighlights();

    const formData = new FormData(addUserForm);
    const fields = {
      Username: formData.get('username')?.trim() || '',
      Password: formData.get('password')?.trim() || '',
      Role: formData.get('role') || '',
      Department: formData.get('department') || ''
    };

    // Track missing fields
    const missingFields = [];
    for (const [key, value] of Object.entries(fields)) {
      if (!value) {
        missingFields.push(key);
        const el = addUserForm.querySelector(`[name="${key.toLowerCase()}"]`);
        if (el) el.classList.add('border-red-500', 'bg-red-50');
      }
    }

    if (missingFields.length > 0) {
      alert("Please fill out all required fields!");
      return;
    }

    formData.append('csrf_token', csrfToken);

    // All fields filled, send AJAX request
    fetch('admin/add_user.php', {
      method: 'POST',
      body: formData
    })
    .then(res => res.json())
    .then(result => {
      if (result.success) {
        alert(result.message);
        addUserForm.reset();
        hideAddModal();
        location.reload();
      } else {
        alert("Error: " + result.message);
      }
    })
    .catch(err => console.error("Add user request failed:", err));
  });

});
