<template>
  <div class="auth-page">
    <!-- Animated background orbs -->
    <div class="bg-orb orb-1"></div>
    <div class="bg-orb orb-2"></div>
    <div class="bg-orb orb-3"></div>

    <div class="auth-card">
      <!-- Logo / Brand -->
      <div class="brand">
        <div class="brand-icon">
          <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            <rect x="9" y="3" width="6" height="4" rx="1" stroke="currentColor" stroke-width="2"/>
            <path d="M9 12l2 2 4-4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </div>
        <h1 class="brand-name">TaskTracker</h1>
        <p class="brand-sub">Student Task Management System</p>
      </div>

      <!-- Title -->
      <div class="card-header">
        <h2 class="card-title">Welcome back</h2>
        <p class="card-subtitle">Sign in to your account to continue</p>
      </div>

      <!-- Error alert -->
      <transition name="slide-down">
        <div v-if="error" class="error-alert" role="alert">
          <svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/><path d="M12 8v4m0 4h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
          {{ error }}
        </div>
      </transition>

      <!-- Form -->
      <form @submit.prevent="handleLogin" class="auth-form" novalidate>
        <div class="form-group">
          <label for="login-email" class="form-label">Email address</label>
          <div class="input-wrapper" :class="{ focused: focusedField === 'email' }">
            <svg class="input-icon" viewBox="0 0 24 24" fill="none">
              <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z" stroke="currentColor" stroke-width="2"/>
              <polyline points="22,6 12,13 2,6" stroke="currentColor" stroke-width="2"/>
            </svg>
            <input
              id="login-email"
              v-model="form.email"
              type="email"
              class="form-input"
              placeholder="you@example.com"
              autocomplete="email"
              @focus="focusedField = 'email'"
              @blur="focusedField = null"
              required
            />
          </div>
        </div>

        <div class="form-group">
          <label for="login-password" class="form-label">Password</label>
          <div class="input-wrapper" :class="{ focused: focusedField === 'password' }">
            <svg class="input-icon" viewBox="0 0 24 24" fill="none">
              <rect x="3" y="11" width="18" height="11" rx="2" ry="2" stroke="currentColor" stroke-width="2"/>
              <path d="M7 11V7a5 5 0 0110 0v4" stroke="currentColor" stroke-width="2"/>
            </svg>
            <input
              id="login-password"
              v-model="form.password"
              :type="showPassword ? 'text' : 'password'"
              class="form-input"
              placeholder="••••••••"
              autocomplete="current-password"
              @focus="focusedField = 'password'"
              @blur="focusedField = null"
              required
            />
            <button type="button" class="toggle-password" @click="showPassword = !showPassword">
              <svg v-if="!showPassword" viewBox="0 0 24 24" fill="none">
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" stroke="currentColor" stroke-width="2"/>
                <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="2"/>
              </svg>
              <svg v-else viewBox="0 0 24 24" fill="none">
                <path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                <line x1="1" y1="1" x2="23" y2="23" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
              </svg>
            </button>
          </div>
        </div>

        <button type="submit" class="submit-btn" :disabled="loading" id="login-submit-btn">
          <span v-if="!loading">Sign In</span>
          <span v-else class="btn-loading">
            <span class="spinner"></span>
            Signing in...
          </span>
        </button>
      </form>

      <!-- Switch to Register -->
      <p class="switch-auth">
        Don't have an account?
        <button type="button" class="switch-link" @click="$emit('switch-to-register')" id="go-to-register-btn">
          Create one now
        </button>
      </p>
    </div>
  </div>
</template>

<script>
import { authService } from "../services/auth";

export default {
  name: "LoginPage",
  emits: ["login-success", "switch-to-register"],
  data() {
    return {
      form: { email: "", password: "" },
      loading: false,
      error: null,
      showPassword: false,
      focusedField: null,
    };
  },
  methods: {
    async handleLogin() {
      this.error = null;

      if (!this.form.email || !this.form.password) {
        this.error = "Please fill in all fields.";
        return;
      }

      this.loading = true;
      try {
        const data = await authService.login(this.form.email, this.form.password);
        this.$emit("login-success", data.user);
      } catch (err) {
        const msg = err.response?.data?.error;
        this.error = msg || "Login failed. Please try again.";
      } finally {
        this.loading = false;
      }
    },
  },
};
</script>

<style scoped>
/* ─── Page layout ─────────────────────────────────────────────────────────── */
.auth-page {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, #0f0c29 0%, #302b63 50%, #24243e 100%);
  position: relative;
  overflow: hidden;
  padding: 1rem;
}

/* ─── Animated background orbs ───────────────────────────────────────────── */
.bg-orb {
  position: absolute;
  border-radius: 50%;
  filter: blur(80px);
  opacity: 0.15;
  animation: floatOrb 8s ease-in-out infinite;
}
.orb-1 {
  width: 400px; height: 400px;
  background: radial-gradient(circle, #6c63ff, transparent);
  top: -100px; left: -100px;
  animation-delay: 0s;
}
.orb-2 {
  width: 300px; height: 300px;
  background: radial-gradient(circle, #4fc3f7, transparent);
  bottom: -80px; right: -80px;
  animation-delay: 3s;
}
.orb-3 {
  width: 200px; height: 200px;
  background: radial-gradient(circle, #f472b6, transparent);
  top: 50%; left: 60%;
  animation-delay: 5s;
}
@keyframes floatOrb {
  0%, 100% { transform: translateY(0) scale(1); }
  50%       { transform: translateY(-30px) scale(1.05); }
}

/* ─── Card ────────────────────────────────────────────────────────────────── */
.auth-card {
  position: relative;
  z-index: 10;
  width: 100%;
  max-width: 440px;
  background: rgba(255, 255, 255, 0.04);
  backdrop-filter: blur(24px);
  -webkit-backdrop-filter: blur(24px);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 24px;
  padding: 2.5rem 2.25rem;
  box-shadow:
    0 25px 50px rgba(0, 0, 0, 0.5),
    inset 0 1px 0 rgba(255, 255, 255, 0.1);
  animation: cardEntrance 0.6s cubic-bezier(0.34, 1.56, 0.64, 1) both;
}
@keyframes cardEntrance {
  from { opacity: 0; transform: translateY(30px) scale(0.96); }
  to   { opacity: 1; transform: translateY(0) scale(1); }
}

/* ─── Brand ───────────────────────────────────────────────────────────────── */
.brand {
  display: flex;
  flex-direction: column;
  align-items: center;
  margin-bottom: 2rem;
}
.brand-icon {
  width: 56px; height: 56px;
  background: linear-gradient(135deg, #6c63ff, #4fc3f7);
  border-radius: 16px;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 0.75rem;
  box-shadow: 0 8px 24px rgba(108, 99, 255, 0.4);
}
.brand-icon svg {
  width: 30px; height: 30px;
  color: white;
}
.brand-name {
  font-size: 1.5rem;
  font-weight: 700;
  color: #fff;
  margin: 0;
  letter-spacing: -0.02em;
}
.brand-sub {
  font-size: 0.8rem;
  color: rgba(255, 255, 255, 0.45);
  margin: 0.25rem 0 0;
}

/* ─── Card header ─────────────────────────────────────────────────────────── */
.card-header {
  margin-bottom: 1.75rem;
  text-align: center;
}
.card-title {
  font-size: 1.6rem;
  font-weight: 700;
  color: #ffffff;
  margin: 0 0 0.4rem;
  letter-spacing: -0.02em;
}
.card-subtitle {
  font-size: 0.9rem;
  color: rgba(255, 255, 255, 0.5);
  margin: 0;
}

/* ─── Error alert ─────────────────────────────────────────────────────────── */
.error-alert {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  background: rgba(239, 68, 68, 0.15);
  border: 1px solid rgba(239, 68, 68, 0.35);
  color: #fca5a5;
  border-radius: 12px;
  padding: 0.75rem 1rem;
  font-size: 0.875rem;
  margin-bottom: 1.25rem;
}
.error-alert svg { width: 18px; height: 18px; flex-shrink: 0; }

/* ─── Form ────────────────────────────────────────────────────────────────── */
.auth-form { display: flex; flex-direction: column; gap: 1.25rem; }
.form-group { display: flex; flex-direction: column; gap: 0.5rem; }
.form-label {
  font-size: 0.85rem;
  font-weight: 500;
  color: rgba(255, 255, 255, 0.7);
  letter-spacing: 0.01em;
}
.input-wrapper {
  position: relative;
  display: flex;
  align-items: center;
  background: rgba(255, 255, 255, 0.06);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 12px;
  transition: border-color 0.2s, background 0.2s, box-shadow 0.2s;
}
.input-wrapper.focused {
  border-color: rgba(108, 99, 255, 0.7);
  background: rgba(255, 255, 255, 0.09);
  box-shadow: 0 0 0 3px rgba(108, 99, 255, 0.15);
}
.input-icon {
  width: 18px; height: 18px;
  color: rgba(255, 255, 255, 0.35);
  margin-left: 1rem;
  flex-shrink: 0;
}
.form-input {
  flex: 1;
  background: transparent;
  border: none;
  outline: none;
  padding: 0.85rem 0.85rem 0.85rem 0.6rem;
  color: #ffffff;
  font-size: 0.95rem;
  font-family: inherit;
}
.form-input::placeholder { color: rgba(255, 255, 255, 0.25); }
.toggle-password {
  background: none; border: none; cursor: pointer;
  padding: 0 0.85rem;
  color: rgba(255, 255, 255, 0.35);
  display: flex; align-items: center;
  transition: color 0.2s;
}
.toggle-password:hover { color: rgba(255, 255, 255, 0.7); }
.toggle-password svg { width: 18px; height: 18px; }

/* ─── Submit button ───────────────────────────────────────────────────────── */
.submit-btn {
  width: 100%;
  padding: 0.9rem;
  background: linear-gradient(135deg, #6c63ff 0%, #4fc3f7 100%);
  color: white;
  font-size: 1rem;
  font-weight: 600;
  border: none;
  border-radius: 12px;
  cursor: pointer;
  transition: opacity 0.2s, transform 0.2s, box-shadow 0.2s;
  box-shadow: 0 8px 24px rgba(108, 99, 255, 0.35);
  margin-top: 0.25rem;
  letter-spacing: 0.01em;
}
.submit-btn:hover:not(:disabled) {
  opacity: 0.92;
  transform: translateY(-1px);
  box-shadow: 0 12px 32px rgba(108, 99, 255, 0.45);
}
.submit-btn:active:not(:disabled) { transform: translateY(0); }
.submit-btn:disabled { opacity: 0.65; cursor: not-allowed; }

.btn-loading { display: flex; align-items: center; justify-content: center; gap: 0.6rem; }
.spinner {
  width: 16px; height: 16px;
  border: 2px solid rgba(255,255,255,0.3);
  border-top-color: white;
  border-radius: 50%;
  animation: spin 0.7s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

/* ─── Switch auth ─────────────────────────────────────────────────────────── */
.switch-auth {
  margin-top: 1.5rem;
  text-align: center;
  font-size: 0.875rem;
  color: rgba(255, 255, 255, 0.45);
}
.switch-link {
  background: none; border: none; cursor: pointer;
  color: #a78bfa;
  font-weight: 600;
  font-size: inherit;
  padding: 0;
  transition: color 0.2s;
}
.switch-link:hover { color: #c4b5fd; text-decoration: underline; }

/* ─── Transition ──────────────────────────────────────────────────────────── */
.slide-down-enter-active { animation: slideDown 0.3s ease; }
.slide-down-leave-active { animation: slideDown 0.3s ease reverse; }
@keyframes slideDown {
  from { opacity: 0; transform: translateY(-8px); }
  to   { opacity: 1; transform: translateY(0); }
}
</style>
