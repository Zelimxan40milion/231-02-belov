const authPanel = document.getElementById("auth-panel");
const appPanel = document.getElementById("app-panel");
const adminPanel = document.getElementById("admin-panel");
const authStatus = document.getElementById("auth-status");

const registerForm = document.getElementById("register-form");
const loginForm = document.getElementById("login-form");
const publishForm = document.getElementById("publish-form");
const adminLoginForm = document.getElementById("admin-login-form");

const registerMessage = document.getElementById("register-message");
const loginMessage = document.getElementById("login-message");
const publishMessage = document.getElementById("publish-message");
const adminLoginMessage = document.getElementById("admin-login-message");

const servicesList = document.getElementById("services-list");
const historyList = document.getElementById("history-list");
const editList = document.getElementById("edit-list");

const searchInput = document.getElementById("search-input");
const searchButton = document.getElementById("search-btn");
const logoutButton = document.getElementById("logout-btn");
const publishNavButton = document.getElementById("btn-publish");
const historyNavButton = document.getElementById("btn-history");
const editNavButton = document.getElementById("btn-edit");
const guestHint = document.getElementById("guest-hint");

const authModal = document.getElementById("auth-modal");
const closeAuthModalButton = document.getElementById("close-auth-modal");
const openRegisterButton = document.getElementById("open-register");
const openLoginButton = document.getElementById("open-login");
const authTabButtons = document.querySelectorAll("[data-auth-tab]");
const authTabLogin = document.getElementById("auth-tab-login");
const authTabRegister = document.getElementById("auth-tab-register");
const adminRefreshButton = document.getElementById("admin-refresh-btn");
const adminLogoutButton = document.getElementById("admin-logout-btn");
const adminUsersList = document.getElementById("admin-users-list");
const adminServicesList = document.getElementById("admin-services-list");

const ADMIN_LOGIN = "admin";
const ADMIN_PASSWORD = "admin123";

function getUsers() {
  return JSON.parse(localStorage.getItem("users") || "[]");
}

function saveUsers(users) {
  localStorage.setItem("users", JSON.stringify(users));
}

function getServices() {
  return JSON.parse(localStorage.getItem("services") || "[]");
}

function saveServices(services) {
  localStorage.setItem("services", JSON.stringify(services));
}

function getCurrentUser() {
  return JSON.parse(localStorage.getItem("currentUser") || "null");
}

function setCurrentUser(user) {
  localStorage.setItem("currentUser", JSON.stringify(user));
}

function clearCurrentUser() {
  localStorage.removeItem("currentUser");
}

function setAdminSession(enabled) {
  localStorage.setItem("isAdmin", enabled ? "1" : "0");
}

function isAdminSession() {
  return localStorage.getItem("isAdmin") === "1";
}

function showPanel(mode) {
  if (!authPanel || !appPanel || !adminPanel || !authStatus) {
    return;
  }
  authPanel.classList.toggle("active", mode === "guest");
  appPanel.classList.toggle("active", mode === "guest" || mode === "user");
  adminPanel.classList.toggle("active", mode === "admin");

  if (mode === "user") {
    authStatus.textContent = "Пользователь авторизован";
  } else if (mode === "admin") {
    authStatus.textContent = "Администратор авторизован";
  } else {
    authStatus.textContent = "Гость";
  }
}

function switchView(groupSelector, targetId) {
  const views = document.querySelectorAll(groupSelector);
  views.forEach((view) => view.classList.remove("active"));
  const target = document.getElementById(targetId);
  if (target) {
    target.classList.add("active");
  }
}

function setUserUiEnabled(enabled) {
  if (publishNavButton) publishNavButton.disabled = !enabled;
  if (historyNavButton) historyNavButton.disabled = !enabled;
  if (editNavButton) editNavButton.disabled = !enabled;
  if (logoutButton) logoutButton.style.display = enabled ? "inline-flex" : "none";
  if (guestHint) guestHint.style.display = enabled ? "none" : "block";
}

function openAuthModal(initialTab) {
  if (!authModal) {
    return;
  }
  authModal.classList.remove("hidden");
  authModal.setAttribute("aria-hidden", "false");
  setAuthTab(initialTab);
}

function closeAuthModal() {
  if (!authModal) {
    return;
  }
  authModal.classList.add("hidden");
  authModal.setAttribute("aria-hidden", "true");
}

function setAuthTab(tab) {
  const isLogin = tab !== "register";
  if (authTabLogin) authTabLogin.classList.toggle("active", isLogin);
  if (authTabRegister) authTabRegister.classList.toggle("active", !isLogin);
  authTabButtons.forEach((btn) => btn.classList.toggle("active", btn.getAttribute("data-auth-tab") === (isLogin ? "login" : "register")));
}

if (openRegisterButton) {
  openRegisterButton.addEventListener("click", () => openAuthModal("register"));
}

if (openLoginButton) {
  openLoginButton.addEventListener("click", () => openAuthModal("login"));
}

authTabButtons.forEach((btn) => {
  btn.addEventListener("click", () => setAuthTab(btn.getAttribute("data-auth-tab")));
});

if (closeAuthModalButton) {
  closeAuthModalButton.addEventListener("click", closeAuthModal);
}

if (authModal) {
  authModal.addEventListener("click", (event) => {
    if (event.target === authModal) {
      closeAuthModal();
    }
  });
}

document.querySelectorAll("[data-target]").forEach((button) => {
  button.addEventListener("click", () => {
    const target = button.getAttribute("data-target");
    if (target) {
      switchView("#auth-panel .view", target);
    }
  });
});

document.querySelectorAll("[data-app-view]").forEach((button) => {
  button.addEventListener("click", () => {
    if (button instanceof HTMLButtonElement && button.disabled) {
      showPanel("guest");
      openAuthModal("login");
      return;
    }
    const target = button.getAttribute("data-app-view");
    if (target) {
      switchView("#app-panel .view", target);
      if (target === "history-view" || target === "edit-view" || target === "search-view") {
        renderServices(searchInput ? searchInput.value : "");
      }
    }
  });
});

document.querySelectorAll("[data-back-auth='true']").forEach((button) => {
  button.addEventListener("click", () => switchView("#auth-panel .view", "auth-placeholder-view"));
});

if (registerForm && registerMessage) {
  registerForm.addEventListener("submit", (event) => {
    event.preventDefault();
    const formData = new FormData(registerForm);
    const newUser = {
      name: String(formData.get("name") || "").trim(),
      email: String(formData.get("email") || "").trim().toLowerCase(),
      password: String(formData.get("password") || ""),
    };

    if (!newUser.name || !newUser.email || newUser.password.length < 6) {
      registerMessage.textContent = "Заполни все поля. Пароль минимум 6 символов.";
      return;
    }

    const users = getUsers();
    const exists = users.some((user) => user.email === newUser.email);
    if (exists) {
      registerMessage.textContent = "Пользователь с такой почтой уже зарегистрирован.";
      return;
    }

    users.push(newUser);
    saveUsers(users);
    registerMessage.textContent = "Регистрация успешна. Теперь выполни вход.";
    registerForm.reset();
    setAuthTab("login");
  });
}

if (loginForm && loginMessage) {
  loginForm.addEventListener("submit", (event) => {
    event.preventDefault();
    const formData = new FormData(loginForm);
    const email = String(formData.get("email") || "").trim().toLowerCase();
    const password = String(formData.get("password") || "");

    const user = getUsers().find((item) => item.email === email && item.password === password);
    if (!user) {
      loginMessage.textContent = "Неверная почта или пароль.";
      return;
    }

    setCurrentUser({ name: user.name, email: user.email });
    setAdminSession(false);
    loginMessage.textContent = "";
    showPanel("user");
    setUserUiEnabled(true);
    switchView("#app-panel .view", "search-view");
    renderServices("");
    closeAuthModal();
  });
}

if (adminLoginForm && adminLoginMessage) {
  adminLoginForm.addEventListener("submit", (event) => {
    event.preventDefault();
    const formData = new FormData(adminLoginForm);
    const login = String(formData.get("login") || "").trim();
    const password = String(formData.get("password") || "");

    if (login !== ADMIN_LOGIN || password !== ADMIN_PASSWORD) {
      adminLoginMessage.textContent = "Неверный логин или пароль администратора.";
      return;
    }

    clearCurrentUser();
    setAdminSession(true);
    adminLoginMessage.textContent = "";
    adminLoginForm.reset();
    showPanel("admin");
    setUserUiEnabled(false);
    renderAdminData();
  });
}

if (logoutButton) {
  logoutButton.addEventListener("click", () => {
    clearCurrentUser();
    setAdminSession(false);
    showPanel("guest");
    setUserUiEnabled(false);
    switchView("#auth-panel .view", "auth-placeholder-view");
  });
}

if (adminLogoutButton) {
  adminLogoutButton.addEventListener("click", () => {
    setAdminSession(false);
    showPanel("guest");
    setUserUiEnabled(false);
    switchView("#auth-panel .view", "admin-login-view");
  });
}

if (adminRefreshButton) {
  adminRefreshButton.addEventListener("click", () => renderAdminData());
}

function renderServices(query) {
  const currentUser = getCurrentUser();
  const services = getServices();
  const normalizedQuery = query.trim().toLowerCase();
  const filtered = normalizedQuery
    ? services.filter((item) => item.title.toLowerCase().includes(normalizedQuery))
    : services;

  if (servicesList) {
    servicesList.innerHTML = "";
    if (!filtered.length) {
      servicesList.innerHTML = '<div class="list-item">Услуг пока нет.</div>';
    } else {
      filtered.forEach((item) => {
        const photoHtml = item.photoDataUrl
          ? `<img class="service-photo" src="${item.photoDataUrl}" alt="Фото услуги: ${item.title}" />`
          : "";
        servicesList.innerHTML += `
          <div class="list-item">
            <div class="list-item-head">
              <strong>${item.title}</strong>
              <span>${item.price} ₽</span>
            </div>
            <p>${item.description}</p>
            <p>Автор: ${item.ownerName}</p>
            ${photoHtml}
          </div>
        `;
      });
    }
  }

  if (historyList) {
    historyList.innerHTML = "";
    const userItems = services.filter((item) => currentUser && item.ownerEmail === currentUser.email);
    if (!userItems.length) {
      historyList.innerHTML = '<div class="list-item">История услуг пока пустая.</div>';
    } else {
      userItems.forEach((item) => {
        historyList.innerHTML += `
          <div class="list-item">
            <div class="list-item-head">
              <strong>${item.title}</strong>
              <span>${new Date(item.createdAt).toLocaleString("ru-RU")}</span>
            </div>
          </div>
        `;
      });
    }
  }

  if (editList) {
    editList.innerHTML = "";
    const own = services.filter((item) => currentUser && item.ownerEmail === currentUser.email);
    if (!own.length) {
      editList.innerHTML = '<div class="list-item">У тебя нет услуг для редактирования.</div>';
    } else {
      own.forEach((item) => {
        const photoHtml = item.photoDataUrl
          ? `<img class="service-photo" src="${item.photoDataUrl}" alt="Фото услуги: ${item.title}" />`
          : "";
        editList.innerHTML += `
          <div class="list-item">
            <div class="list-item-head">
              <strong>${item.title}</strong>
              <span>${item.price} ₽</span>
            </div>
            <p>${item.description}</p>
            ${photoHtml}
            <div class="item-actions">
              <button class="small-btn" data-edit-id="${item.id}" type="button">Редактировать</button>
              <button class="small-btn delete" data-delete-id="${item.id}" type="button">Удалить</button>
            </div>
          </div>
        `;
      });
    }
  }
}

if (publishForm && publishMessage) {
  publishForm.addEventListener("submit", async (event) => {
    event.preventDefault();
    const currentUser = getCurrentUser();
    if (!currentUser) {
      publishMessage.textContent = "Сначала выполни вход.";
      return;
    }

    const formData = new FormData(publishForm);
    const file = formData.get("photo");
    let photoDataUrl = "";
    if (file instanceof File && file.size > 0) {
      if (!file.type.startsWith("image/")) {
        publishMessage.textContent = "Можно загрузить только изображение.";
        return;
      }
      if (file.size > 1_500_000) {
        publishMessage.textContent = "Фото слишком большое. Выбери изображение до 1.5 МБ.";
        return;
      }
      photoDataUrl = await new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.onload = () => resolve(String(reader.result || ""));
        reader.onerror = () => reject(new Error("read failed"));
        reader.readAsDataURL(file);
      });
    }

    const service = {
      id: crypto.randomUUID(),
      title: String(formData.get("title") || "").trim(),
      description: String(formData.get("description") || "").trim(),
      price: Number(formData.get("price") || 0),
      photoDataUrl,
      ownerName: currentUser.name,
      ownerEmail: currentUser.email,
      createdAt: new Date().toISOString(),
    };

    if (!service.title || !service.description || service.price < 0) {
      publishMessage.textContent = "Заполни корректно название, описание и цену.";
      return;
    }

    const services = getServices();
    services.unshift(service);
    saveServices(services);
    publishForm.reset();
    publishMessage.textContent = "Услуга размещена успешно.";
    renderServices("");
  });
}

if (searchButton && searchInput) {
  searchButton.addEventListener("click", () => renderServices(searchInput.value));
}

document.addEventListener("click", (event) => {
  const target = event.target;
  if (!(target instanceof HTMLElement)) {
    return;
  }

  const deleteId = target.getAttribute("data-delete-id");
  if (deleteId) {
    const services = getServices().filter((item) => item.id !== deleteId);
    saveServices(services);
    renderServices(searchInput ? searchInput.value : "");
    renderAdminData();
    return;
  }

  const editId = target.getAttribute("data-edit-id");
  if (editId) {
    const services = getServices();
    const index = services.findIndex((item) => item.id === editId);
    if (index === -1) {
      return;
    }
    const newTitle = prompt("Новое название услуги", services[index].title);
    if (!newTitle) {
      return;
    }
    services[index].title = newTitle.trim();
    saveServices(services);
    renderServices(searchInput ? searchInput.value : "");
    renderAdminData();
  }

  const adminDeleteId = target.getAttribute("data-admin-delete-id");
  if (adminDeleteId) {
    const services = getServices().filter((item) => item.id !== adminDeleteId);
    saveServices(services);
    renderServices(searchInput ? searchInput.value : "");
    renderAdminData();
  }
});

function renderAdminData() {
  if (!adminUsersList || !adminServicesList) {
    return;
  }

  const users = getUsers();
  const services = getServices();

  adminUsersList.innerHTML = "";
  if (!users.length) {
    adminUsersList.innerHTML = '<div class="list-item">Пользователей пока нет.</div>';
  } else {
    users.forEach((user) => {
      adminUsersList.innerHTML += `
        <div class="list-item">
          <div class="list-item-head">
            <strong>${user.name}</strong>
            <span>${user.email}</span>
          </div>
        </div>
      `;
    });
  }

  adminServicesList.innerHTML = "";
  if (!services.length) {
    adminServicesList.innerHTML = '<div class="list-item">Услуги пока не добавлены.</div>';
  } else {
    services.forEach((item) => {
      const photoHtml = item.photoDataUrl
        ? `<img class="service-photo" src="${item.photoDataUrl}" alt="Фото услуги: ${item.title}" />`
        : "";
      adminServicesList.innerHTML += `
        <div class="list-item">
          <div class="list-item-head">
            <strong>${item.title}</strong>
            <span>${item.price} ₽</span>
          </div>
          <p>${item.description}</p>
          <p>Автор: ${item.ownerName} (${item.ownerEmail})</p>
          ${photoHtml}
          <div class="item-actions">
            <button class="small-btn delete" type="button" data-admin-delete-id="${item.id}">Удалить услугу</button>
          </div>
        </div>
      `;
    });
  }
}

const user = getCurrentUser();
const isAdmin = isAdminSession();

if (isAdmin) {
  showPanel("admin");
  setUserUiEnabled(false);
  renderAdminData();
} else if (user) {
  showPanel("user");
  setUserUiEnabled(true);
  renderServices("");
} else {
  showPanel("guest");
  setUserUiEnabled(false);
  renderServices("");
}
