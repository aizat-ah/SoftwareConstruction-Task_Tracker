import axios from "axios";

const authApi = axios.create({
  baseURL: "/api",
  headers: { "Content-Type": "application/json" },
});

// Attach the Bearer token to every auth request automatically
authApi.interceptors.request.use((config) => {
  const token = localStorage.getItem("auth_token");
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

export const authService = {
  /**
   * Register a new user. Automatically saves the session on success.
   */
  async register(username, email, password) {
    const response = await authApi.post("/auth?action=register", {
      username,
      email,
      password,
    });
    this._saveSession(response.data);
    return response.data;
  },

  /**
   * Login an existing user. Automatically saves the session on success.
   */
  async login(email, password) {
    const response = await authApi.post("/auth?action=login", {
      email,
      password,
    });
    this._saveSession(response.data);
    return response.data;
  },

  /**
   * Logout the current user. Clears session regardless of server response.
   */
  async logout() {
    try {
      const token = this.getToken();
      if (token) {
        await authApi.post(
          "/auth?action=logout",
          {},
          { headers: { Authorization: `Bearer ${token}` } }
        );
      }
    } catch {
      // Proceed with local cleanup even if the server call fails
    } finally {
      localStorage.removeItem("auth_token");
      localStorage.removeItem("auth_user");
    }
  },

  /**
   * Fetch the current user's profile (fresh from the server).
   * GET /profile
   */
  async getProfile() {
    const response = await authApi.get("/profile");
    return response.data;
  },

  /**
   * Update the current user's username and email.
   * PUT /profile — keeps the locally stored user in sync on success.
   */
  async updateProfile(username, email) {
    const response = await authApi.put("/profile", { username, email });
    if (response.data.user) {
      this._updateStoredUser(response.data.user);
    }
    return response.data;
  },

  /**
   * Change the current user's password.
   * PUT /profile/password
   */
  async changePassword(currentPassword, newPassword) {
    const response = await authApi.put("/profile/password", {
      currentPassword,
      newPassword,
    });
    // The server rotates the auth token on password change — store the fresh
    // one so the session stays valid and no re-login is needed.
    if (response.data.token) {
      localStorage.setItem("auth_token", response.data.token);
    }
    return response.data;
  },

  /**
   * Delete the current user's account, then clear the local session.
   * DELETE /profile
   */
  async deleteAccount() {
    const response = await authApi.delete("/profile");
    localStorage.removeItem("auth_token");
    localStorage.removeItem("auth_user");
    return response.data;
  },

  // ─── Helpers ────────────────────────────────────────────────────────────────

  /**
   * Merge fields into the stored user without touching the token.
   */
  _updateStoredUser(partialUser) {
    const current = this.getUser() || {};
    const merged = { ...current, ...partialUser };
    localStorage.setItem("auth_user", JSON.stringify(merged));
    return merged;
  },

  _saveSession(data) {
    localStorage.setItem("auth_token", data.token);
    localStorage.setItem("auth_user", JSON.stringify(data.user));
  },

  getToken() {
    return localStorage.getItem("auth_token");
  },

  getUser() {
    const raw = localStorage.getItem("auth_user");
    try {
      return raw ? JSON.parse(raw) : null;
    } catch {
      return null;
    }
  },

  isLoggedIn() {
    return !!this.getToken();
  },
};
