<template>
  <div class="floating-pomodoro">
    <!-- Slide-in panel holding the actual timer.
         Kept mounted (v-show) so the countdown keeps running while closed. -->
    <transition name="panel">
      <div v-show="open" class="pomo-panel">
        <div class="panel-head">
          <span class="panel-title">
            <i class="fas fa-stopwatch"></i> Focus Timer
          </span>
          <button class="close-btn" title="Close" @click="open = false">
            <i class="fas fa-times"></i>
          </button>
        </div>
        <PomodoroTimer @state="onState" />
      </div>
    </transition>

    <!-- Floating action button -->
    <button
      class="pomo-fab"
      :class="[state.mode, { running: state.running }]"
      :title="open ? 'Hide timer' : 'Open Pomodoro'"
      @click="open = !open"
    >
      <span v-if="state.running" class="fab-time">{{ state.display }}</span>
      <i v-else class="fas fa-stopwatch fab-icon"></i>
      <span v-if="state.running" class="pulse-dot"></span>
    </button>
  </div>
</template>

<script>
import PomodoroTimer from "./PomodoroTimer.vue";

export default {
  name: "FloatingPomodoro",
  components: { PomodoroTimer },
  data() {
    return {
      open: false,
      state: { display: "25:00", running: false, mode: "focus" },
    };
  },
  methods: {
    onState(s) {
      this.state = s;
    },
  },
};
</script>

<style scoped>
.floating-pomodoro {
  position: fixed;
  right: 24px;
  bottom: 24px;
  z-index: 1500;
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 14px;
}

/* ─── Floating action button ─────────────────────────────────────────────── */
.pomo-fab {
  position: relative;
  min-width: 60px;
  height: 60px;
  padding: 0 14px;
  border: none;
  border-radius: 30px;
  background: linear-gradient(135deg, #6c63ff, #4fc3f7);
  color: #fff;
  cursor: pointer;
  box-shadow: 0 8px 24px rgba(108, 99, 255, 0.45);
  display: flex;
  align-items: center;
  justify-content: center;
  transition: transform 0.2s, box-shadow 0.2s, background 0.3s;
}
.pomo-fab:hover {
  transform: translateY(-2px);
  box-shadow: 0 12px 30px rgba(108, 99, 255, 0.55);
}
.pomo-fab.break {
  background: linear-gradient(135deg, #22c55e, #4ade80);
  box-shadow: 0 8px 24px rgba(34, 197, 94, 0.45);
}
.fab-icon {
  font-size: 1.5rem;
}
.fab-time {
  font-size: 1.1rem;
  font-weight: 700;
  font-variant-numeric: tabular-nums;
  letter-spacing: 0.02em;
}
.pulse-dot {
  position: absolute;
  top: 8px;
  right: 10px;
  width: 9px;
  height: 9px;
  border-radius: 50%;
  background: #fff;
  box-shadow: 0 0 0 0 rgba(255, 255, 255, 0.7);
  animation: pulse 1.4s infinite;
}
@keyframes pulse {
  0% {
    box-shadow: 0 0 0 0 rgba(255, 255, 255, 0.6);
  }
  70% {
    box-shadow: 0 0 0 8px rgba(255, 255, 255, 0);
  }
  100% {
    box-shadow: 0 0 0 0 rgba(255, 255, 255, 0);
  }
}

/* ─── Panel ──────────────────────────────────────────────────────────────── */
.pomo-panel {
  width: 300px;
  max-width: calc(100vw - 40px);
  background: #fff;
  border-radius: 14px;
  box-shadow: 0 16px 40px rgba(0, 0, 0, 0.22);
  overflow: hidden;
}
.panel-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 10px 14px;
  background: #f8f9fa;
  border-bottom: 1px solid #eef0f5;
}
.panel-title {
  font-size: 0.9rem;
  font-weight: 600;
  color: #2c3e50;
  display: flex;
  align-items: center;
  gap: 7px;
}
.panel-title i {
  color: #6c63ff;
}
.close-btn {
  background: none;
  border: none;
  color: #adb5bd;
  cursor: pointer;
  font-size: 1rem;
  padding: 4px 6px;
  border-radius: 6px;
  transition: color 0.2s, background 0.2s;
}
.close-btn:hover {
  color: #ef4444;
  background: rgba(239, 68, 68, 0.08);
}

/* The timer card inside the panel shouldn't cast its own big shadow */
.pomo-panel :deep(.pomodoro-card) {
  box-shadow: none;
  border-radius: 0;
}

/* ─── Panel transition ───────────────────────────────────────────────────── */
.panel-enter-active,
.panel-leave-active {
  transition: opacity 0.2s ease, transform 0.2s ease;
}
.panel-enter-from,
.panel-leave-to {
  opacity: 0;
  transform: translateY(12px) scale(0.97);
}

@media (max-width: 600px) {
  .floating-pomodoro {
    right: 16px;
    bottom: 16px;
  }
}
</style>
