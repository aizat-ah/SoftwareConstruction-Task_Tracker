<template>
  <div class="subtask-checklist">
    <div class="checklist-header">
      <div class="progress-summary">
        <div :class="['progress-percent', `text-${progressVariant}`]">
          {{ progressPercent }}%
        </div>
        <div class="progress-meta">
          <span class="progress-label">
            {{ completedCount }} of {{ subtasks.length }}
            step{{ subtasks.length === 1 ? "" : "s" }} done
          </span>
          <b-progress :max="100" height="8px" class="progress-bar-custom">
            <b-progress-bar
              :value="progressPercent"
              :variant="progressVariant"
            />
          </b-progress>
        </div>
      </div>

      <div v-if="completedCount > 0" class="clear-completed">
        <template v-if="!confirmClearCompleted">
          <button class="link-btn" @click="confirmClearCompleted = true">
            <i class="fas fa-broom"></i> Clear completed
          </button>
        </template>
        <template v-else>
          <span class="clear-confirm-text">
            Clear {{ completedCount }} completed step{{
              completedCount === 1 ? "" : "s"
            }}?
          </span>
          <button class="confirm-btn confirm-yes" @click="clearCompleted">
            Yes
          </button>
          <button
            class="confirm-btn confirm-cancel"
            @click="confirmClearCompleted = false"
          >
            Cancel
          </button>
        </template>
      </div>
    </div>

    <div v-if="loading" class="checklist-status">Loading subtasks...</div>
    <div v-else-if="error" class="checklist-status text-danger">{{ error }}</div>

    <template v-else>
      <div v-if="sortedSubtasks.length === 0" class="empty-state">
        <i class="fas fa-clipboard-list"></i>
        <p>No subtasks yet. Add one below to break this task down.</p>
      </div>

      <ul v-else class="subtask-items">
        <li
          v-for="(subtask, index) in sortedSubtasks"
          :key="subtask.id"
          :class="['subtask-item', { 'is-completed': subtask.is_completed }]"
        >
          <b-form-checkbox
            :model-value="subtask.is_completed"
            @update:model-value="toggleComplete(subtask, $event)"
          />

          <div class="subtask-title-wrapper">
            <input
              v-if="editingId === subtask.id"
              v-model="editingTitle"
              class="edit-input"
              @keyup.enter="saveEdit(subtask)"
              @keyup.esc="cancelEdit"
              @blur="saveEdit(subtask)"
            />
            <span
              v-else
              :class="['subtask-title', { completed: subtask.is_completed }]"
              @dblclick="startEdit(subtask)"
            >
              {{ subtask.title }}
            </span>
          </div>

          <div v-if="confirmDeleteId === subtask.id" class="delete-confirm">
            <span class="clear-confirm-text">Delete this step?</span>
            <button
              class="confirm-btn confirm-yes"
              @click="confirmDelete(subtask)"
            >
              Yes
            </button>
            <button class="confirm-btn confirm-cancel" @click="cancelDelete">
              Cancel
            </button>
          </div>
          <div v-else class="subtask-actions">
            <button
              class="icon-btn"
              :disabled="index === 0"
              title="Move up"
              @click="moveUp(index)"
            >
              <i class="fas fa-chevron-up"></i>
            </button>
            <button
              class="icon-btn"
              :disabled="index === sortedSubtasks.length - 1"
              title="Move down"
              @click="moveDown(index)"
            >
              <i class="fas fa-chevron-down"></i>
            </button>
            <span class="action-divider"></span>
            <button class="icon-btn" title="Edit" @click="startEdit(subtask)">
              <i class="fas fa-pen"></i>
            </button>
            <button
              class="icon-btn text-danger"
              title="Delete"
              @click="askDeleteConfirm(subtask)"
            >
              <i class="fas fa-trash"></i>
            </button>
          </div>
        </li>
      </ul>
    </template>

    <div class="add-subtask">
      <div class="add-input-wrapper">
        <i class="fas fa-plus add-input-icon"></i>
        <input
          ref="addInput"
          v-model="newTitle"
          class="add-input"
          placeholder="Add a subtask..."
          @keyup.enter="addSubtask"
          @keyup.esc="clearAddInput"
        />
      </div>
      <b-button
        variant="primary"
        size="sm"
        :disabled="!newTitle.trim()"
        @click="addSubtask"
      >
        Add
      </b-button>
    </div>
  </div>
</template>

<script>
import { subtaskService } from "../services/api";

export default {
  name: "SubtaskChecklist",
  props: {
    taskId: {
      type: [Number, String],
      required: true,
    },
  },
  data() {
    return {
      subtasks: [],
      loading: false,
      error: null,
      newTitle: "",
      editingId: null,
      editingTitle: "",
      confirmDeleteId: null,
      confirmClearCompleted: false,
    };
  },
  computed: {
    sortedSubtasks() {
      return [...this.subtasks].sort((a, b) => a.sort_order - b.sort_order);
    },
    completedCount() {
      return this.subtasks.filter((s) => s.is_completed).length;
    },
    progressPercent() {
      if (this.subtasks.length === 0) return 0;
      return Math.round((this.completedCount / this.subtasks.length) * 100);
    },
    progressVariant() {
      if (this.progressPercent < 30) return "danger";
      if (this.progressPercent < 70) return "warning";
      return "success";
    },
  },
  watch: {
    taskId: {
      immediate: true,
      handler() {
        this.loadSubtasks();
      },
    },
  },
  methods: {
    async loadSubtasks() {
      this.loading = true;
      this.error = null;
      try {
        const response = await subtaskService.getSubtasks(this.taskId);
        this.subtasks = response.data;
      } catch (error) {
        this.error = "Failed to load subtasks. Please try again later.";
        console.error("Error loading subtasks:", error);
      } finally {
        this.loading = false;
      }
    },
    async addSubtask() {
      const title = this.newTitle.trim();
      if (!title) return;
      try {
        const response = await subtaskService.createSubtask(this.taskId, title);
        this.subtasks.push(response.data);
        this.newTitle = "";
        this.$nextTick(() => this.$refs.addInput?.focus());
      } catch (error) {
        this.error = "Failed to add subtask. Please try again later.";
        console.error("Error adding subtask:", error);
      }
    },
    clearAddInput(event) {
      this.newTitle = "";
      event.target.blur();
    },
    async toggleComplete(subtask, isCompleted) {
      const previous = subtask.is_completed;
      subtask.is_completed = isCompleted;
      try {
        await subtaskService.toggleSubtask(subtask.id, isCompleted);
      } catch (error) {
        subtask.is_completed = previous;
        this.error = "Failed to update subtask. Please try again later.";
        console.error("Error toggling subtask:", error);
      }
    },
    startEdit(subtask) {
      this.editingId = subtask.id;
      this.editingTitle = subtask.title;
    },
    cancelEdit() {
      this.editingId = null;
      this.editingTitle = "";
    },
    async saveEdit(subtask) {
      if (this.editingId !== subtask.id) return;
      const title = this.editingTitle.trim();
      this.editingId = null;
      if (!title || title === subtask.title) return;

      const previous = subtask.title;
      subtask.title = title;
      try {
        await subtaskService.updateSubtask(subtask.id, { title });
      } catch (error) {
        subtask.title = previous;
        this.error = "Failed to update subtask. Please try again later.";
        console.error("Error updating subtask:", error);
      }
    },
    askDeleteConfirm(subtask) {
      this.confirmDeleteId = subtask.id;
    },
    cancelDelete() {
      this.confirmDeleteId = null;
    },
    async confirmDelete(subtask) {
      this.confirmDeleteId = null;
      try {
        await subtaskService.deleteSubtask(subtask.id);
        this.subtasks = this.subtasks.filter((s) => s.id !== subtask.id);
      } catch (error) {
        this.error = "Failed to delete subtask. Please try again later.";
        console.error("Error deleting subtask:", error);
      }
    },
    async clearCompleted() {
      this.confirmClearCompleted = false;
      try {
        await subtaskService.clearCompleted(this.taskId);
        this.subtasks = this.subtasks.filter((s) => !s.is_completed);
      } catch (error) {
        this.error = "Failed to clear completed subtasks. Please try again later.";
        console.error("Error clearing completed subtasks:", error);
      }
    },
    async moveUp(index) {
      if (index === 0) return;
      await this.swapOrder(index, index - 1);
    },
    async moveDown(index) {
      if (index === this.sortedSubtasks.length - 1) return;
      await this.swapOrder(index, index + 1);
    },
    async swapOrder(indexA, indexB) {
      const ordered = this.sortedSubtasks;
      const a = ordered[indexA];
      const b = ordered[indexB];
      const orderA = a.sort_order;
      const orderB = b.sort_order;
      a.sort_order = orderB;
      b.sort_order = orderA;
      try {
        await Promise.all([
          subtaskService.updateSubtask(a.id, { sort_order: a.sort_order }),
          subtaskService.updateSubtask(b.id, { sort_order: b.sort_order }),
        ]);
      } catch (error) {
        a.sort_order = orderA;
        b.sort_order = orderB;
        this.error = "Failed to reorder subtasks. Please try again later.";
        console.error("Error reordering subtasks:", error);
      }
    },
  },
};
</script>

<style scoped>
.subtask-checklist {
  padding: 0.5rem 0;
}

.checklist-header {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  margin-bottom: 1.25rem;
  padding: 1rem 1.1rem;
  background: #f8f9fb;
  border-radius: 12px;
}

.progress-summary {
  display: flex;
  align-items: center;
  gap: 1rem;
}

.progress-percent {
  font-size: 1.85rem;
  font-weight: 700;
  line-height: 1;
  min-width: 3.5rem;
}

.progress-meta {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 0.4rem;
}

.progress-label {
  font-size: 0.85rem;
  font-weight: 500;
  color: #6c757d;
}

.clear-completed {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  justify-content: flex-end;
}

.link-btn {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  border: none;
  background: transparent;
  color: #6c757d;
  font-size: 0.8rem;
  font-weight: 500;
  padding: 0;
  cursor: pointer;
}

.link-btn:hover {
  color: #dc3545;
}

.clear-confirm-text {
  font-size: 0.85rem;
  color: #6c757d;
}

.confirm-btn {
  border: none;
  border-radius: 4px;
  padding: 0.15rem 0.6rem;
  font-size: 0.8rem;
  cursor: pointer;
}

.confirm-btn.confirm-yes {
  background: #dc3545;
  color: white;
}

.confirm-btn.confirm-yes:hover {
  background: #bb2d3b;
}

.confirm-btn.confirm-cancel {
  background: #e9ecef;
  color: #2c3e50;
}

.confirm-btn.confirm-cancel:hover {
  background: #dee2e6;
}

.delete-confirm {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  flex-shrink: 0;
}

.progress-bar-custom {
  width: 100%;
  border-radius: 999px;
  overflow: hidden;
}

.checklist-status {
  padding: 1rem 0;
  color: #6c757d;
}

.empty-state {
  text-align: center;
  padding: 2.5rem 1rem;
  margin-bottom: 1rem;
  color: #adb5bd;
  border: 1.5px dashed #e9ecef;
  border-radius: 12px;
}

.empty-state i {
  font-size: 1.85rem;
  margin-bottom: 0.5rem;
  display: block;
  color: #ced4da;
}

.empty-state p {
  margin: 0;
  color: #6c757d;
}

.subtask-items {
  list-style: none;
  padding: 0;
  margin: 0 0 1rem 0;
}

.subtask-item {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.6rem 0.5rem;
  margin: 0 -0.5rem;
  border-radius: 8px;
  border-bottom: 1px solid #f0f1f3;
  transition: background-color 0.15s ease;
}

.subtask-item:hover {
  background-color: #f8f9fa;
}

.subtask-item:last-child {
  border-bottom: none;
}

.subtask-item.is-completed {
  opacity: 0.6;
}

.subtask-item.is-completed:hover {
  opacity: 0.85;
}

.subtask-title-wrapper {
  flex: 1;
  min-width: 0;
}

.subtask-title {
  color: #2c3e50;
  word-break: break-word;
}

.subtask-title.completed {
  text-decoration: line-through;
  color: #6c757d;
}

.edit-input,
.add-input {
  width: 100%;
  padding: 0.375rem 0.5rem;
  border: 1px solid #ced4da;
  border-radius: 6px;
  font-size: 0.95rem;
  transition: border-color 0.15s ease, box-shadow 0.15s ease;
}

.add-input {
  padding-left: 1.9rem;
}

.edit-input:focus,
.add-input:focus {
  outline: none;
  border-color: #86b7fe;
  box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.15);
}

.add-input-wrapper {
  position: relative;
  flex: 1;
}

.add-input-icon {
  position: absolute;
  left: 0.7rem;
  top: 50%;
  transform: translateY(-50%);
  color: #adb5bd;
  font-size: 0.75rem;
  pointer-events: none;
}

.subtask-actions {
  display: flex;
  align-items: center;
  gap: 0.15rem;
  flex-shrink: 0;
}

.action-divider {
  width: 1px;
  height: 16px;
  background: #e9ecef;
  margin: 0 0.25rem;
}

.icon-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 28px;
  height: 28px;
  border: none;
  background: transparent;
  color: #6c757d;
  border-radius: 6px;
  cursor: pointer;
  transition: background-color 0.15s ease, color 0.15s ease;
}

.icon-btn:hover:not(:disabled) {
  background: #e9ecef;
  color: #2c3e50;
}

.icon-btn:disabled {
  opacity: 0.3;
  cursor: not-allowed;
}

.icon-btn.text-danger:hover:not(:disabled) {
  background: #fde8ea;
}

.add-subtask {
  display: flex;
  gap: 0.5rem;
  align-items: center;
  padding-top: 1rem;
  border-top: 1px solid #f0f1f3;
}
</style>
