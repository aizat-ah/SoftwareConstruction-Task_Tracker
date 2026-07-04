import axios from "axios";
import { authService } from "./auth";

// const API_URL = "https://student-task-tracker-system.kesug.com/index.php";
// const API_URL = "https://utm-web-tech.as.r.appspot.com/index.php";  // production (App Engine)
const API_URL = "/api"; // local dev — proxied to http://localhost:8000 by vue.config.js

const api = axios.create({
  baseURL: API_URL,
  headers: {
    "Content-Type": "application/json",
  },
});

// Attach Bearer token to every request automatically
api.interceptors.request.use((config) => {
  const token = authService.getToken();
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

export const taskService = {
  // Get all tasks
  getAllTasks() {
    return api.get("/tasks");
  },

  // Get a single task
  getTask(id) {
    return api.get(`/tasks/${id}`);
  },

  // Create a new task
  createTask(task) {
    return api.post("/tasks", task);
  },

  // Update a task
  updateTask(id, task) {
    return api.put(`/tasks/${id}`, task);
  },

  // Delete a task
  deleteTask(id) {
    return api.delete(`/tasks/${id}`);
  },
};
