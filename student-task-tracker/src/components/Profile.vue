<template>
  <div class="profile">
    <div class="profile-header">
      <h2 class="profile-title">My Profile</h2>
    </div>

    <!-- Global feedback banners -->
    <transition name="slide-down">
      <div v-if="banner.message" :class="['banner', banner.type]" role="alert">
        {{ banner.message }}
      </div>
    </transition>

    <!-- Identity card -->
    <div class="profile-card identity-card">
      <div class="identity-avatar">{{ userInitial }}</div>
      <div class="identity-info">
        <h3 class="identity-name">{{ form.username || "—" }}</h3>
        <p class="identity-email">{{ form.email || "—" }}</p>
        <p v-if="joinedDate" class="identity-joined">
          Member since {{ joinedDate }}
        </p>
      </div>
    </div>

    <div class="cards-grid">
      <!-- Edit profile -->
      <div class="profile-card">
        <h3 class="card-heading">Account details</h3>
        <form @submit.prevent="saveProfile" class="card-form" novalidate>
          <div class="field">
            <label for="pf-username">Username</label>
            <input
              id="pf-username"
              v-model="form.username"
              type="text"
              autocomplete="username"
              placeholder="Your username"
            />
          </div>
          <div class="field">
            <label for="pf-email">Email address</label>
            <input
              id="pf-email"
              v-model="form.email"
              type="email"
              autocomplete="email"
              placeholder="you@example.com"
            />
          </div>
          <button type="submit" class="btn btn-primary" :disabled="savingProfile">
            {{ savingProfile ? "Saving..." : "Save changes" }}
          </button>
        </form>
      </div>

      <!-- Change password -->
      <div class="profile-card">
        <h3 class="card-heading">Change password</h3>
        <form @submit.prevent="savePassword" class="card-form" novalidate>
          <div class="field">
            <label for="pf-current">Current password</label>
            <input
              id="pf-current"
              v-model="pw.current"
              type="password"
              autocomplete="current-password"
              placeholder="••••••••"
            />
          </div>
          <div class="field">
            <label for="pf-new">New password</label>
            <input
              id="pf-new"
              v-model="pw.next"
              type="password"
              autocomplete="new-password"
              placeholder="At least 6 characters"
            />
          </div>
          <div class="field">
            <label for="pf-confirm">Confirm new password</label>
            <input
              id="pf-confirm"
              v-model="pw.confirm"
              type="password"
              autocomplete="new-password"
              placeholder="Re-enter new password"
            />
          </div>
          <button type="submit" class="btn btn-primary" :disabled="savingPassword">
            {{ savingPassword ? "Updating..." : "Update password" }}
          </button>
        </form>
      </div>
    </div>

    <!-- Danger zone -->
    <div class="profile-card danger-card">
      <h3 class="card-heading danger-heading">Danger zone</h3>
      <p class="danger-text">
        Deleting your account is permanent and will remove all of your tasks.
        This cannot be undone.
      </p>
      <button
        v-if="!confirmingDelete"
        class="btn btn-danger"
        @click="confirmingDelete = true"
      >
        Delete my account
      </button>
      <div v-else class="confirm-delete">
        <span>Are you sure? This is permanent.</span>
        <div class="confirm-actions">
          <button class="btn btn-ghost" @click="confirmingDelete = false">
            Cancel
          </button>
          <button class="btn btn-danger" :disabled="deleting" @click="deleteAccount">
            {{ deleting ? "Deleting..." : "Yes, delete forever" }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { authService } from "../services/auth";

export default {
  name: "UserProfile",
  emits: ["profile-updated", "account-deleted"],
  data() {
    return {
      form: { username: "", email: "" },
      createdAt: null,
      pw: { current: "", next: "", confirm: "" },
      savingProfile: false,
      savingPassword: false,
      deleting: false,
      confirmingDelete: false,
      banner: { message: "", type: "success" },
    };
  },
  computed: {
    userInitial() {
      return (this.form.username || "U").charAt(0).toUpperCase();
    },
    joinedDate() {
      if (!this.createdAt) return "";
      const d = new Date(this.createdAt.replace(" ", "T"));
      if (isNaN(d)) return "";
      return d.toLocaleDateString(undefined, {
        year: "numeric",
        month: "long",
        day: "numeric",
      });
    },
  },
  methods: {
    showBanner(message, type = "success") {
      this.banner = { message, type };
      window.clearTimeout(this._bannerTimer);
      this._bannerTimer = window.setTimeout(() => {
        this.banner = { message: "", type };
      }, 4000);
    },

    async loadProfile() {
      // Seed from cached user for instant render
      const cached = authService.getUser() || {};
      this.form.username = cached.username || "";
      this.form.email = cached.email || "";
      try {
        const data = await authService.getProfile();
        this.form.username = data.username;
        this.form.email = data.email;
        this.createdAt = data.created_at;
      } catch (err) {
        this.showBanner(this.errMsg(err, "Could not load profile."), "error");
      }
    },

    async saveProfile() {
      if (!this.form.username.trim() || !this.form.email.trim()) {
        this.showBanner("Username and email are required.", "error");
        return;
      }
      this.savingProfile = true;
      try {
        const res = await authService.updateProfile(
          this.form.username.trim(),
          this.form.email.trim()
        );
        this.showBanner("Profile updated successfully.");
        this.$emit("profile-updated", res.user);
      } catch (err) {
        this.showBanner(this.errMsg(err, "Failed to update profile."), "error");
      } finally {
        this.savingProfile = false;
      }
    },

    async savePassword() {
      if (!this.pw.current || !this.pw.next) {
        this.showBanner("Please fill in all password fields.", "error");
        return;
      }
      if (this.pw.next.length < 6) {
        this.showBanner("New password must be at least 6 characters.", "error");
        return;
      }
      if (this.pw.next !== this.pw.confirm) {
        this.showBanner("New passwords do not match.", "error");
        return;
      }
      this.savingPassword = true;
      try {
        await authService.changePassword(this.pw.current, this.pw.next);
        this.showBanner("Password changed successfully.");
        this.pw = { current: "", next: "", confirm: "" };
      } catch (err) {
        this.showBanner(this.errMsg(err, "Failed to change password."), "error");
      } finally {
        this.savingPassword = false;
      }
    },

    async deleteAccount() {
      this.deleting = true;
      try {
        await authService.deleteAccount();
        this.$emit("account-deleted");
      } catch (err) {
        this.showBanner(this.errMsg(err, "Failed to delete account."), "error");
        this.deleting = false;
      }
    },

    errMsg(err, fallback) {
      return err?.response?.data?.error || fallback;
    },
  },
  mounted() {
    this.loadProfile();
  },
  beforeUnmount() {
    window.clearTimeout(this._bannerTimer);
  },
};
</script>

<style scoped>
.profile {
  padding: 20px;
  background-color: #f8f9fa;
  height: calc(100vh - 64px);
  overflow-y: auto;
  max-width: 900px;
  margin: 0 auto;
  width: 100%;
}

.profile-header {
  margin-bottom: 20px;
}
.profile-title {
  color: #2c3e50;
  font-size: 1.6rem;
  font-weight: 700;
  margin: 0;
}

/* ─── Banner ──────────────────────────────────────────────────────────────── */
.banner {
  border-radius: 10px;
  padding: 0.8rem 1rem;
  font-size: 0.9rem;
  font-weight: 500;
  margin-bottom: 18px;
}
.banner.success {
  background: #e6f7ee;
  color: #1a7f52;
  border: 1px solid #b7e4cd;
}
.banner.error {
  background: #fdecea;
  color: #b3261e;
  border: 1px solid #f5c2c0;
}

/* ─── Cards ───────────────────────────────────────────────────────────────── */
.profile-card {
  background: #fff;
  border-radius: 12px;
  padding: 24px;
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.08);
  margin-bottom: 20px;
}

.identity-card {
  display: flex;
  align-items: center;
  gap: 18px;
}
.identity-avatar {
  width: 64px;
  height: 64px;
  border-radius: 50%;
  background: linear-gradient(135deg, #6c63ff, #4fc3f7);
  color: #fff;
  font-size: 1.6rem;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.identity-name {
  margin: 0;
  font-size: 1.25rem;
  font-weight: 700;
  color: #2c3e50;
}
.identity-email {
  margin: 2px 0 0;
  color: #6c757d;
  font-size: 0.95rem;
}
.identity-joined {
  margin: 6px 0 0;
  color: #adb5bd;
  font-size: 0.82rem;
}

.cards-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
  gap: 20px;
}
.cards-grid .profile-card {
  margin-bottom: 0;
}

.card-heading {
  font-size: 1.1rem;
  font-weight: 600;
  color: #2c3e50;
  margin: 0 0 18px;
  padding-bottom: 10px;
  border-bottom: 2px solid #f1f1f1;
}

.card-form {
  display: flex;
  flex-direction: column;
  gap: 14px;
}
.field {
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.field label {
  font-size: 0.82rem;
  font-weight: 500;
  color: #6c757d;
}
.field input {
  border: 1px solid #dee2e6;
  border-radius: 8px;
  padding: 0.65rem 0.8rem;
  font-size: 0.95rem;
  font-family: inherit;
  color: #2c3e50;
  outline: none;
  transition: border-color 0.2s, box-shadow 0.2s;
}
.field input:focus {
  border-color: #6c63ff;
  box-shadow: 0 0 0 3px rgba(108, 99, 255, 0.15);
}

/* ─── Buttons ─────────────────────────────────────────────────────────────── */
.btn {
  border: none;
  border-radius: 8px;
  padding: 0.65rem 1.1rem;
  font-size: 0.9rem;
  font-weight: 600;
  cursor: pointer;
  font-family: inherit;
  transition: opacity 0.2s, transform 0.15s, background 0.2s;
  align-self: flex-start;
}
.btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
.btn-primary {
  background: linear-gradient(135deg, #6c63ff, #4fc3f7);
  color: #fff;
}
.btn-primary:hover:not(:disabled) {
  transform: translateY(-1px);
}
.btn-danger {
  background: #ef4444;
  color: #fff;
}
.btn-danger:hover:not(:disabled) {
  background: #dc2626;
}
.btn-ghost {
  background: #f1f3f5;
  color: #495057;
}
.btn-ghost:hover:not(:disabled) {
  background: #e9ecef;
}

/* ─── Danger zone ─────────────────────────────────────────────────────────── */
.danger-card {
  border: 1px solid #f5c2c0;
}
.danger-heading {
  color: #b3261e;
  border-bottom-color: #f8d7da;
}
.danger-text {
  color: #6c757d;
  font-size: 0.9rem;
  margin: 0 0 16px;
}
.confirm-delete {
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.confirm-delete > span {
  font-weight: 600;
  color: #b3261e;
  font-size: 0.9rem;
}
.confirm-actions {
  display: flex;
  gap: 10px;
}

/* ─── Transition ──────────────────────────────────────────────────────────── */
.slide-down-enter-active,
.slide-down-leave-active {
  transition: opacity 0.25s ease, transform 0.25s ease;
}
.slide-down-enter-from,
.slide-down-leave-to {
  opacity: 0;
  transform: translateY(-8px);
}

@media (max-width: 600px) {
  .cards-grid {
    grid-template-columns: 1fr;
  }
}
</style>
