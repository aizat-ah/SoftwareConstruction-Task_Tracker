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
        <h2 class="card-title">Create account</h2>
        <p class="card-subtitle">Join and start tracking your tasks today</p>
      </div>

      <!-- Error alert -->
      <transition name="slide-down">
        <div v-if="error" class="error-alert" role="alert">
          <svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/><path d="M12 8v4m0 4h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
          {{ error }}
        </div>
      </transition>

      <!-- Success alert -->
      <transition name="slide-down">
        <div v-if="success" class="success-alert" role="alert">
          <svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/><path d="M9 12l2 2 4-4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
          Account created! Signing you in...
        </div>
      </transition>

      <!-- Form -->
      <form @submit.prevent="handleRegister" class="auth-form" novalidate>
        <!-- Username -->
        <div class="form-group">
          <label for="reg-username" class="form-label">Username</label>
          <div class="input-wrapper" :class="{ focused: focusedField === 'username', error: fieldErrors.username }">
            <svg class="input-icon" viewBox="0 0 24 24" fill="none">
              <path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2" stroke="currentColor" stroke-width="2"/>
              <circle cx="12" cy="7" r="4" stroke="currentColor" stroke-width="2"/>
            </svg>
            <input
              id="reg-username"
              v-model="form.username"
              type="text"
              class="form-input"
              placeholder="e.g. johndoe"
              autocomplete="username"
              @focus="focusedField = 'username'"
              @blur="focusedField = null; validateField('username')"
              required
            />
          </div>
          <span v-if="fieldErrors.username" class="field-error">{{ fieldErrors.username }}</span>
        </div>

        <!-- Email -->
        <div class="form-group">
          <label for="reg-email" class="form-label">Email address</label>
          <div class="input-wrapper" :class="{ focused: focusedField === 'email', error: fieldErrors.email }">
            <svg class="input-icon" viewBox="0 0 24 24" fill="none">
              <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z" stroke="currentColor" stroke-width="2"/>
              <polyline points="22,6 12,13 2,6" stroke="currentColor" stroke-width="2"/>
            </svg>
            <input
              id="reg-email"
              v-model="form.email"
              type="email"
              class="form-input"
              placeholder="you@example.com"
              autocomplete="email"
              @focus="focusedField = 'email'"
              @blur="focusedField = null; validateField('email')"
              required
            />
          </div>
          <span v-if="fieldErrors.email" class="field-error">{{ fieldErrors.email }}</span>
        </div>

        <!-- Password -->
        <div class="form-group">
          <label for="reg-password" class="form-label">Password</label>
          <div class="input-wrapper" :class="{ focused: focusedField === 'password', error: fieldErrors.password }">
            <svg class="input-icon" viewBox="0 0 24 24" fill="none">
              <rect x="3" y="11" width="18" height="11" rx="2" ry="2" stroke="currentColor" stroke-width="2"/>
              <path d="M7 11V7a5 5 0 0110 0v4" stroke="currentColor" stroke-width="2"/>
            </svg>
            <input
              id="reg-password"
              v-model="form.password"
              :type="showPassword ? 'text' : 'password'"
              class="form-input"
              placeholder="Min. 6 characters"
              autocomplete="new-password"
              @focus="focusedField = 'password'"
              @blur="focusedField = null; validateField('password')"
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
          <!-- Password strength bar -->
          <div class="password-strength" v-if="form.password">
            <div class="strength-bar">
              <div class="strength-fill" :class="passwordStrength.class" :style="{ width: passwordStrength.width }"></div>
            </div>
            <span class="strength-label" :class="passwordStrength.class">{{ passwordStrength.label }}</span>
          </div>
          <span v-if="fieldErrors.password" class="field-error">{{ fieldErrors.password }}</span>
        </div>

        <!-- Confirm Password -->
        <div class="form-group">
          <label for="reg-confirm-password" class="form-label">Confirm password</label>
          <div class="input-wrapper" :class="{ focused: focusedField === 'confirmPassword', error: fieldErrors.confirmPassword }">
            <svg class="input-icon" viewBox="0 0 24 24" fill="none">
              <polyline points="20 6 9 17 4 12" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            <input
              id="reg-confirm-password"
              v-model="form.confirmPassword"
              :type="showPassword ? 'text' : 'password'"
              class="form-input"
              placeholder="Repeat your password"
              autocomplete="new-password"
              @focus="focusedField = 'confirmPassword'"
              @blur="focusedField = null; validateField('confirmPassword')"
              required
            />
          </div>
          <span v-if="fieldErrors.confirmPassword" class="field-error">{{ fieldErrors.confirmPassword }}</span>
        </div>

        <button type="submit" class="submit-btn" :disabled="loading" id="register-submit-btn">
          <span v-if="!loading">Create Account</span>
          <span v-else class="btn-loading">
            <span class="spinner"></span>
            Creating account...
          </span>
        </button>
      </form>

      <!-- Switch to Login -->
      <p class="switch-auth">
        Already have an account?
        <button type="button" class="switch-link" @click="$emit('switch-to-login')" id="go-to-login-btn">
          Sign in
        </button>
      </p>
    </div>
  </div>
</template>

<script>
import { authService } from "../services/auth";

export default {
  name: "RegisterPage",
  emits: ["register-success", "switch-to-login"],
  data() {
    return {
      form: { username: "", email: "", password: "", confirmPassword: "" },
      fieldErrors: { username: "", email: "", password: "", confirmPassword: "" },
      loading: false,
      error: null,
      success: false,
      showPassword: false,
      focusedField: null,
    };
  },
  computed: {
    passwordStrength() {
      const p = this.form.password;
      if (!p) return { label: "", class: "", width: "0%" };
      let score = 0;
      if (p.length >= 6)  score++;
      if (p.length >= 10) score++;
      if (/[A-Z]/.test(p)) score++;
      if (/[0-9]/.test(p)) score++;
      if (/[^A-Za-z0-9]/.test(p)) score++;
      const levels = [
        { label: "Weak",   class: "weak",   width: "25%"  },
        { label: "Fair",   class: "fair",   width: "50%"  },
        { label: "Good",   class: "good",   width: "75%"  },
        { label: "Strong", class: "strong", width: "100%" },
      ];
      return levels[Math.min(score - 1, 3)] || levels[0];
    },
  },
  methods: {
    validateField(field) {
      this.fieldErrors[field] = "";
      if (field === "username" && !this.form.username.trim()) {
        this.fieldErrors.username = "Username is required";
      }
      if (field === "email") {
        if (!this.form.email) {
          this.fieldErrors.email = "Email is required";
        } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(this.form.email)) {
          this.fieldErrors.email = "Enter a valid email address";
        }
      }
      if (field === "password" && this.form.password.length > 0 && this.form.password.length < 6) {
        this.fieldErrors.password = "Password must be at least 6 characters";
      }
      if (field === "confirmPassword" && this.form.confirmPassword && this.form.password !== this.form.confirmPassword) {
        this.fieldErrors.confirmPassword = "Passwords do not match";
      }
    },

    validateAll() {
      ["username", "email", "password", "confirmPassword"].forEach((f) => this.validateField(f));
      return Object.values(this.fieldErrors).every((e) => !e);
    },

    async handleRegister() {
      this.error = null;
      if (!this.validateAll()) return;

      if (this.form.password !== this.form.confirmPassword) {
        this.error = "Passwords do not match.";
        return;
      }

      this.loading = true;
      try {
        const data = await authService.register(
          this.form.username,
          this.form.email,
          this.form.password
        );
        this.success = true;
        setTimeout(() => this.$emit("register-success", data.user), 1000);
      } catch (err) {
        const msg = err.response?.data?.error;
        this.error = msg || "Registration failed. Please try again.";
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
  background: radial-gradient(circle, #f472b6, transparent);
  top: -100px; right: -100px;
  animation-delay: 0s;
}
.orb-2 {
  width: 300px; height: 300px;
  background: radial-gradient(circle, #6c63ff, transparent);
  bottom: -80px; left: -80px;
  animation-delay: 3s;
}
.orb-3 {
  width: 200px; height: 200px;
  background: radial-gradient(circle, #4fc3f7, transparent);
  top: 40%; left: 10%;
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
  margin-bottom: 1.5rem;
}
.brand-icon {
  width: 56px; height: 56px;
  background: linear-gradient(135deg, #f472b6, #6c63ff);
  border-radius: 16px;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 0.75rem;
  box-shadow: 0 8px 24px rgba(244, 114, 182, 0.4);
}
.brand-icon svg { width: 30px; height: 30px; color: white; }
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
  margin-bottom: 1.5rem;
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

/* ─── Alerts ──────────────────────────────────────────────────────────────── */
.error-alert, .success-alert {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  border-radius: 12px;
  padding: 0.75rem 1rem;
  font-size: 0.875rem;
  margin-bottom: 1.25rem;
}
.error-alert {
  background: rgba(239, 68, 68, 0.15);
  border: 1px solid rgba(239, 68, 68, 0.35);
  color: #fca5a5;
}
.success-alert {
  background: rgba(34, 197, 94, 0.15);
  border: 1px solid rgba(34, 197, 94, 0.35);
  color: #86efac;
}
.error-alert svg, .success-alert svg { width: 18px; height: 18px; flex-shrink: 0; }

/* ─── Form ────────────────────────────────────────────────────────────────── */
.auth-form { display: flex; flex-direction: column; gap: 1rem; }
.form-group { display: flex; flex-direction: column; gap: 0.4rem; }
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
  border-color: rgba(244, 114, 182, 0.7);
  background: rgba(255, 255, 255, 0.09);
  box-shadow: 0 0 0 3px rgba(244, 114, 182, 0.15);
}
.input-wrapper.error {
  border-color: rgba(239, 68, 68, 0.6);
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
  padding: 0.8rem 0.85rem 0.8rem 0.6rem;
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

/* ─── Field errors ────────────────────────────────────────────────────────── */
.field-error {
  font-size: 0.78rem;
  color: #fca5a5;
  padding-left: 0.25rem;
}

/* ─── Password strength ───────────────────────────────────────────────────── */
.password-strength {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  margin-top: 0.25rem;
}
.strength-bar {
  flex: 1;
  height: 4px;
  background: rgba(255,255,255,0.1);
  border-radius: 99px;
  overflow: hidden;
}
.strength-fill {
  height: 100%;
  border-radius: 99px;
  transition: width 0.4s ease, background-color 0.4s ease;
}
.strength-label { font-size: 0.75rem; font-weight: 500; white-space: nowrap; }
.weak   { background: #ef4444; color: #ef4444; }
.fair   { background: #f97316; color: #f97316; }
.good   { background: #eab308; color: #eab308; }
.strong { background: #22c55e; color: #22c55e; }

/* ─── Submit button ───────────────────────────────────────────────────────── */
.submit-btn {
  width: 100%;
  padding: 0.9rem;
  background: linear-gradient(135deg, #f472b6 0%, #6c63ff 100%);
  color: white;
  font-size: 1rem;
  font-weight: 600;
  border: none;
  border-radius: 12px;
  cursor: pointer;
  transition: opacity 0.2s, transform 0.2s, box-shadow 0.2s;
  box-shadow: 0 8px 24px rgba(244, 114, 182, 0.35);
  margin-top: 0.5rem;
  letter-spacing: 0.01em;
}
.submit-btn:hover:not(:disabled) {
  opacity: 0.92;
  transform: translateY(-1px);
  box-shadow: 0 12px 32px rgba(244, 114, 182, 0.45);
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
  color: #c084fc;
  font-weight: 600;
  font-size: inherit;
  padding: 0;
  transition: color 0.2s;
}
.switch-link:hover { color: #e879f9; text-decoration: underline; }

/* ─── Transitions ─────────────────────────────────────────────────────────── */
.slide-down-enter-active { animation: slideDown 0.3s ease; }
.slide-down-leave-active { animation: slideDown 0.3s ease reverse; }
@keyframes slideDown {
  from { opacity: 0; transform: translateY(-8px); }
  to   { opacity: 1; transform: translateY(0); }
}
</style>
