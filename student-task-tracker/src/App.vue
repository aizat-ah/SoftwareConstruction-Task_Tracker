<template>
  <div id="app">
    <!-- ── AUTH GATE ─────────────────────────────────────────────────────── -->
    <transition name="fade" mode="out-in">
      <LoginPage
        v-if="!isAuthenticated && authView === 'login'"
        key="login"
        @login-success="handleAuthSuccess"
        @switch-to-register="authView = 'register'"
      />
      <RegisterPage
        v-else-if="!isAuthenticated && authView === 'register'"
        key="register"
        @register-success="handleAuthSuccess"
        @switch-to-login="authView = 'login'"
      />

      <!-- ── MAIN APP ────────────────────────────────────────────────────── -->
      <div v-else key="app" class="app-shell">
        <nav class="custom-navbar">
          <div class="nav-container">
            <span class="nav-brand">Student Task Tracker</span>
            <div class="nav-links">
              <a
                href="#"
                :class="['nav-link', { active: currentView === 'dashboard' }]"
                @click.prevent="currentView = 'dashboard'"
              >
                Dashboard
              </a>
              <a
                href="#"
                :class="['nav-link', { active: currentView === 'tasks' }]"
                @click.prevent="currentView = 'tasks'"
              >
                Tasks
              </a>
            </div>

            <!-- User info + Logout -->
            <div class="nav-user">
              <button
                class="user-chip"
                :class="{ active: currentView === 'profile' }"
                @click="currentView = 'profile'"
                title="View profile"
              >
                <div class="user-avatar">{{ userInitial }}</div>
                <span class="username-label">{{ currentUser.username }}</span>
              </button>
              <button class="logout-btn" @click="handleLogout" id="logout-btn" title="Sign out">
                <svg viewBox="0 0 24 24" fill="none">
                  <path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                  <polyline points="16 17 21 12 16 7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                  <line x1="21" y1="12" x2="9" y2="12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                </svg>
                <span>Sign out</span>
              </button>
            </div>
          </div>
        </nav>

        <div class="main-container">
          <component
            :is="currentComponent"
            :task="selectedTask"
            @edit-task="handleEditTask"
            @task-saved="handleTaskSaved"
            @cancel="currentView = 'tasks'"
            @add-task="handleAddTask"
            @profile-updated="handleProfileUpdated"
            @account-deleted="handleAccountDeleted"
          />
        </div>
      </div>
    </transition>
  </div>
</template>

<script>
import TaskDashboard from "./components/Dashboard.vue";
import TaskList from "./components/TaskList.vue";
import TaskForm from "./components/TaskForm.vue";
import LoginPage from "./components/LoginPage.vue";
import RegisterPage from "./components/RegisterPage.vue";
import Profile from "./components/Profile.vue";
import { authService } from "./services/auth";

export default {
  name: "App",
  components: {
    TaskDashboard,
    TaskList,
    TaskForm,
    LoginPage,
    RegisterPage,
    Profile,
  },
  data() {
    return {
      // Auth state
      isAuthenticated: authService.isLoggedIn(),
      authView: "login",           // 'login' | 'register'
      currentUser: authService.getUser() || {},

      // App state (existing)
      currentView: "dashboard",
      selectedTask: null,
    };
  },
  computed: {
    currentComponent() {
      switch (this.currentView) {
        case "dashboard":  return "TaskDashboard";
        case "tasks":      return "TaskList";
        case "add-task":   return "TaskForm";
        case "edit-task":  return "TaskForm";
        case "profile":    return "Profile";
        default:           return "TaskDashboard";
      }
    },
    userInitial() {
      const name = this.currentUser?.username || "";
      return name.charAt(0).toUpperCase() || "U";
    },
  },
  methods: {
    // Called after successful login or register
    handleAuthSuccess(user) {
      this.currentUser = user;
      this.isAuthenticated = true;
      this.currentView = "dashboard";
    },

    async handleLogout() {
      await authService.logout();
      this.isAuthenticated = false;
      this.currentUser = {};
      this.currentView = "dashboard";
      this.authView = "login";
    },

    handleEditTask(task) {
      this.selectedTask = task;
      this.currentView = "edit-task";
    },
    handleTaskSaved() {
      this.currentView = "tasks";
      this.selectedTask = null;
    },
    handleAddTask() {
      this.selectedTask = null;
      this.currentView = "add-task";
    },

    // Keep navbar in sync after the user edits their profile
    handleProfileUpdated(user) {
      if (user) {
        this.currentUser = { ...this.currentUser, ...user };
      }
    },

    // Account was deleted — session is already cleared, return to login
    handleAccountDeleted() {
      this.isAuthenticated = false;
      this.currentUser = {};
      this.currentView = "dashboard";
      this.authView = "login";
    },
  },
};
</script>

<style>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');

* { box-sizing: border-box; }

#app {
  font-family: "Inter", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto,
    Oxygen, Ubuntu, Cantarell, "Open Sans", "Helvetica Neue", sans-serif;
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
  color: #2c3e50;
  min-height: 100vh;
}

.app-shell {
  background-color: #f8f9fa;
  min-height: 100vh;
  display: flex;
  flex-direction: column;
}

/* ─── Navbar ─────────────────────────────────────────────────────────────── */
.custom-navbar {
  background: white;
  padding: 0.8rem 0;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.08);
  position: sticky;
  top: 0;
  z-index: 1000;
  height: 64px;
}
.nav-container {
  max-width: 1400px;
  margin: 0 auto;
  padding: 0 2rem;
  display: flex;
  justify-content: space-between;
  align-items: center;
  height: 100%;
}
.nav-brand {
  color: #2c3e50;
  font-size: 1.25rem;
  font-weight: 700;
  letter-spacing: -0.02em;
  margin-right: auto;
}
.nav-links {
  display: flex;
  gap: 2rem;
  margin: 0 2rem;
  align-items: center;
}
.nav-link {
  color: #6c757d;
  text-decoration: none;
  font-weight: 500;
  padding: 0.5rem 0;
  transition: all 0.2s ease;
  position: relative;
}
.nav-link:hover { color: #6c63ff; text-decoration: none; }
.nav-link.active { color: #6c63ff; }
.nav-link.active::after {
  content: "";
  position: absolute;
  bottom: 0; left: 0;
  width: 100%; height: 2px;
  background-color: #6c63ff;
  border-radius: 2px;
}

/* ─── Nav user section ───────────────────────────────────────────────────── */
.nav-user {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  margin-left: auto;
}
.user-chip {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  background: none;
  border: 1px solid transparent;
  border-radius: 999px;
  padding: 0.2rem 0.6rem 0.2rem 0.2rem;
  cursor: pointer;
  font-family: inherit;
  transition: background 0.2s, border-color 0.2s;
}
.user-chip:hover { background: rgba(108, 99, 255, 0.06); }
.user-chip.active { border-color: rgba(108, 99, 255, 0.4); }
.user-avatar {
  width: 34px; height: 34px;
  background: linear-gradient(135deg, #6c63ff, #4fc3f7);
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-weight: 700;
  font-size: 0.85rem;
  flex-shrink: 0;
}
.username-label {
  font-size: 0.875rem;
  font-weight: 500;
  color: #2c3e50;
  max-width: 100px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.logout-btn {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  background: none;
  border: 1px solid rgba(239, 68, 68, 0.3);
  color: #ef4444;
  padding: 0.4rem 0.85rem;
  border-radius: 8px;
  font-size: 0.8rem;
  font-weight: 500;
  cursor: pointer;
  transition: background 0.2s, border-color 0.2s;
  font-family: inherit;
}
.logout-btn svg { width: 15px; height: 15px; }
.logout-btn:hover {
  background: rgba(239, 68, 68, 0.08);
  border-color: rgba(239, 68, 68, 0.6);
}

/* ─── Main content ───────────────────────────────────────────────────────── */
.main-container {
  flex: 1;
  max-width: 1400px;
  width: 100%;
  margin: 0 auto;
  padding: 0 1rem;
  overflow: hidden;
}

/* ─── Page transition ────────────────────────────────────────────────────── */
.fade-enter-active, .fade-leave-active { transition: opacity 0.25s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }

@media (max-width: 768px) {
  .nav-container { padding: 0 1rem; }
  .username-label { display: none; }
}
</style>
