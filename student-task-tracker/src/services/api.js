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

export const subtaskService = {
  // Get all subtasks for a task
  getSubtasks(taskId) {
    return api.get(`/subtasks/task/${taskId}`);
  },

  // Create a new subtask under a task
  createSubtask(taskId, title) {
    return api.post(`/subtasks/task/${taskId}`, { title });
  },

  // Update a subtask's title/completed/sort_order (partial update)
  updateSubtask(id, changes) {
    return api.put(`/subtasks/${id}`, changes);
  },

  // Toggle a subtask's completed state
  toggleSubtask(id, isCompleted) {
    return api.put(`/subtasks/${id}`, { is_completed: isCompleted });
  },

  // Persist a new sort order for a set of subtasks
  reorderSubtasks(orderedIds) {
    return Promise.all(
      orderedIds.map((id, index) =>
        api.put(`/subtasks/${id}`, { sort_order: index })
      )
    );
  },

  // Delete a subtask
  deleteSubtask(id) {
    return api.delete(`/subtasks/${id}`);
  },

  // Delete all completed subtasks for a task in one request
  clearCompleted(taskId) {
    return api.delete(`/subtasks/task/${taskId}/completed`);
  },
};
