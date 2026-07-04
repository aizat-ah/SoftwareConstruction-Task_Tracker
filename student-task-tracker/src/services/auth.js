import axios from "axios";

const authApi = axios.create({
  baseURL: "/api",
  headers: { "Content-Type": "application/json" },
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

  // ─── Helpers ────────────────────────────────────────────────────────────────

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
