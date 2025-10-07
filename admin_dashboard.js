document.addEventListener("DOMContentLoaded", () => {

  // ---------- MODAL UTILITIES ----------
  function showModal(modal) {
    modal.classList.remove("hidden");
    modal.classList.add("flex");
  }

  function hideModal(modal) {
    modal.classList.add("hidden");
    modal.classList.remove("flex");
  }

  function setupModalClose(modal, ...closeButtons) {
    closeButtons.forEach(btn => btn.addEventListener("click", () => hideModal(modal)));
    modal.addEventListener("click", e => { if (e.target === modal) hideModal(modal); });
  }

  // ---------- ADD USER MODAL ----------
  const addUserModal = document.getElementById('addUserModal');
  const addUserBtn = document.getElementById('addUserBtn');
  const closeAddUserModal = document.getElementById('closeAddUserModal');
  const cancelAddUser = document.getElementById('cancelAddUser');
  const addUserForm = document.getElementById('addUserForm');

  addUserBtn.addEventListener('click', () => showModal(addUserModal));
  setupModalClose(addUserModal, closeAddUserModal, cancelAddUser);

  addUserForm.addEventListener('submit', e => {
    e.preventDefault();
    const formData = new FormData(addUserForm);
    // TODO: send to server via fetch
    addUserForm.reset();
    hideModal(addUserModal);
  });

  // ---------- VIEW USER MODAL ----------
  const viewUserModal = document.getElementById('ViewUserModal');
  const closeViewUserModal = document.getElementById('closeViewUserModal');
  const closeViewUser = document.getElementById('closeViewUser');
  const editFromView = document.getElementById("editFromView");

  setupModalClose(viewUserModal, closeViewUserModal, closeViewUser);

  function populateViewModal(user) {
    document.getElementById("viewUserAvatar").textContent = user.username ? user.username.charAt(0).toUpperCase() : "?";
    document.getElementById("viewUserName").textContent = user.username ?? "N/A";
    document.getElementById("viewUserRole").textContent = user.role ?? "N/A";
    document.getElementById("viewUserDepartment").textContent = user.department ?? "N/A";
    document.getElementById("viewUserJoined").textContent = user.joined ?? "N/A";
    document.getElementById("viewUserLastSeen").textContent = user.last_seen ?? "Never";

    const statusEl = document.getElementById("viewUserStatus");
    if (user.is_active == 1) {
      statusEl.textContent = "Active";
      statusEl.className = "inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800";
    } else {
      statusEl.textContent = "Inactive";
      statusEl.className = "inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800";
    }

    showModal(viewUserModal);

    editFromView.onclick = () => {
      openEditUserModal(user.id);
      hideModal(viewUserModal);
    };
  }

  // ---------- EDIT USER MODAL ----------
  const editUserModal = document.getElementById('editUserModal');
  const closeEditUserModal = document.getElementById('closeEditUserModal');
  const cancelEditUser = document.getElementById('cancelEditUser');
  const editUserForm = document.getElementById('editUserForm');

  setupModalClose(editUserModal, closeEditUserModal, cancelEditUser);

  function openEditUserModal(userId) {
    fetch(`admin/get_user.php?id=${userId}`)
      .then(res => res.json())
      .then(user => {
        if (user.error) { alert(user.error); return; }

        document.getElementById("editUserId").value = user.id;
        document.getElementById("editUsername").value = user.username ?? "";
        document.getElementById("editRole").value = user.role ?? "";
        document.getElementById("editDepartment").value = user.department_id ?? "";
        document.getElementById("editPassword").value = ""; // always empty
        document.getElementById("editStatus").value = user.is_active == 1 ? 1 : 0;

        showModal(editUserModal);
      })
      .catch(err => console.error("Failed to fetch user:", err));
  }

  editUserForm.addEventListener("submit", e => {
    e.preventDefault();
    const formData = new FormData(editUserForm);

    // Only include password if not empty
    if (!formData.get("password")) formData.delete("password");

    fetch("admin/update_user.php", { method: "POST", body: formData })
      .then(res => res.json())
      .then(result => {
        if (result.success) {
          alert("User updated successfully!");
          location.reload();
        } else {
          alert("Error updating user: " + result.error);
        }
      })
      .catch(err => console.error("Update failed:", err));
  });

  // ---------- BUTTON HOOKS ----------
  function hookUserButtons(selector, callback) {
    document.querySelectorAll(selector).forEach(button => {
      button.addEventListener("click", () => callback(button.dataset.id));
    });
  }

  // View buttons
  hookUserButtons(".view-user", userId => {
    fetch(`admin/get_user.php?id=${userId}`)
      .then(res => res.json())
      .then(user => { if (!user.error) populateViewModal(user); })
      .catch(err => console.error("Failed to load user:", err));
  });

  // Edit buttons
  hookUserButtons(".edit-user", openEditUserModal);

  // ---------- DELETE BUTTON FUNCTIONALITY ----------
  hookUserButtons(".delete-user", userId => {
    if (!confirm("Are you sure you want to delete this user?")) return;

    fetch("admin/delete_user.php", {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: `id=${userId}`
    })
    .then(res => res.json())
    .then(result => {
      if (result.success) {
        alert("User deleted successfully!");
        // Remove user row from table if exists
        const row = document.querySelector(`button.delete-user[data-id='${userId}']`)?.closest("tr");
        if (row) row.remove();
      } else {
        alert("Error deleting user: " + result.error);
      }
    })
    .catch(err => console.error("Delete request failed:", err));
  });

});
