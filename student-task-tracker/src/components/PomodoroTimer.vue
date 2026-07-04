<template>
  <div class="pomodoro-card" :class="mode">
    <div class="pomo-header">
      <h3 class="pomo-title">
        <i class="fas fa-stopwatch"></i> Pomodoro
      </h3>
      <button class="icon-btn" title="Settings" @click="showSettings = !showSettings">
        <i class="fas fa-cog"></i>
      </button>
    </div>

    <!-- Settings panel -->
    <transition name="collapse">
      <div v-if="showSettings" class="pomo-settings">
        <div class="setting">
          <label>Focus (min)</label>
          <input type="number" min="1" max="120" v-model.number="focusInput" />
        </div>
        <div class="setting">
          <label>Break (min)</label>
          <input type="number" min="1" max="60" v-model.number="breakInput" />
        </div>
        <button class="apply-btn" @click="applySettings">Apply</button>
      </div>
    </transition>

    <!-- Mode label -->
    <div class="mode-label">
      {{ mode === "focus" ? "Focus time" : "Break time" }}
    </div>

    <!-- Circular timer -->
    <div class="ring-wrap">
      <svg class="ring" viewBox="0 0 120 120">
        <circle class="ring-track" cx="60" cy="60" r="52" />
        <circle
          class="ring-progress"
          cx="60"
          cy="60"
          r="52"
          :stroke-dasharray="circumference"
          :stroke-dashoffset="dashOffset"
        />
      </svg>
      <div class="ring-time">{{ display }}</div>
    </div>

    <!-- Controls -->
    <div class="pomo-controls">
      <button class="ctrl-btn primary" @click="toggle">
        <i :class="running ? 'fas fa-pause' : 'fas fa-play'"></i>
        {{ running ? "Pause" : "Start" }}
      </button>
      <button class="ctrl-btn ghost" @click="reset" title="Reset">
        <i class="fas fa-redo"></i>
      </button>
      <button class="ctrl-btn ghost" @click="skip" title="Skip to next">
        <i class="fas fa-forward"></i>
      </button>
    </div>

    <div class="pomo-footer">
      <i class="fas fa-check-circle"></i>
      {{ completedFocus }} focus session{{ completedFocus === 1 ? "" : "s" }} done
    </div>
  </div>
</template>

<script>
export default {
  name: "PomodoroTimer",
  emits: ["state"],
  data() {
    return {
      focusMin: 25,
      breakMin: 5,
      focusInput: 25,
      breakInput: 5,
      mode: "focus", // 'focus' | 'break'
      secondsLeft: 25 * 60,
      running: false,
      completedFocus: 0,
      showSettings: false,
      circumference: 2 * Math.PI * 52,
      timerId: null,
    };
  },
  computed: {
    totalSeconds() {
      return (this.mode === "focus" ? this.focusMin : this.breakMin) * 60;
    },
    display() {
      const m = Math.floor(this.secondsLeft / 60);
      const s = this.secondsLeft % 60;
      return `${String(m).padStart(2, "0")}:${String(s).padStart(2, "0")}`;
    },
    dashOffset() {
      const fraction = this.secondsLeft / this.totalSeconds;
      return this.circumference * (1 - fraction);
    },
  },
  methods: {
    toggle() {
      this.running ? this.pause() : this.start();
    },
    start() {
      if (this.running) return;
      this.running = true;
      this.timerId = window.setInterval(this.tick, 1000);
    },
    pause() {
      this.running = false;
      window.clearInterval(this.timerId);
      this.timerId = null;
    },
    tick() {
      if (this.secondsLeft > 0) {
        this.secondsLeft -= 1;
      } else {
        this.completeSession();
      }
    },
    completeSession() {
      this.beep();
      if (this.mode === "focus") {
        this.completedFocus += 1;
        this.mode = "break";
      } else {
        this.mode = "focus";
      }
      this.secondsLeft = this.totalSeconds;
      // keep running straight into the next session
    },
    skip() {
      if (this.mode === "focus") this.mode = "break";
      else this.mode = "focus";
      this.secondsLeft = this.totalSeconds;
    },
    reset() {
      this.pause();
      this.mode = "focus";
      this.secondsLeft = this.focusMin * 60;
    },
    applySettings() {
      this.focusMin = Math.min(120, Math.max(1, this.focusInput || 25));
      this.breakMin = Math.min(60, Math.max(1, this.breakInput || 5));
      this.focusInput = this.focusMin;
      this.breakInput = this.breakMin;
      this.showSettings = false;
      // Reset the current session to the new duration
      this.pause();
      this.secondsLeft = this.totalSeconds;
    },
    beep() {
      // Pleasant multi-note chime via the Web Audio API — no audio asset needed.
      try {
        const Ctx = window.AudioContext || window.webkitAudioContext;
        if (!Ctx) return;
        const ctx = new Ctx();
        const now = ctx.currentTime;

        // Focus finished  → cheerful rising major arpeggio (C5-E5-G5-C6).
        // Break finished   → grounded "back to work" chime (G5-E5-C5).
        const focusEnded = this.mode === "focus";
        const notes = focusEnded
          ? [523.25, 659.25, 783.99, 1046.5]
          : [783.99, 659.25, 523.25];

        const master = ctx.createGain();
        master.gain.value = 0.9;
        master.connect(ctx.destination);

        const step = 0.16; // spacing between notes
        const dur = 0.55; // per-note ring-out

        notes.forEach((freq, i) => {
          const t = now + i * step;
          // Two slightly detuned oscillators per note for a warmer, bell-like tone.
          [0, 4].forEach((detune, j) => {
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.type = j === 0 ? "triangle" : "sine";
            osc.frequency.value = freq;
            osc.detune.value = detune;
            osc.connect(gain);
            gain.connect(master);
            gain.gain.setValueAtTime(0.0001, t);
            gain.gain.exponentialRampToValueAtTime(0.35, t + 0.02);
            gain.gain.exponentialRampToValueAtTime(0.0001, t + dur);
            osc.start(t);
            osc.stop(t + dur + 0.05);
          });
        });

        // Close the context once the whole chime has finished playing.
        const totalMs = (notes.length * step + dur + 0.1) * 1000;
        window.setTimeout(() => ctx.close(), totalMs);
      } catch {
        // Audio not available — silently ignore
      }
    },
    emitState() {
      this.$emit("state", {
        display: this.display,
        running: this.running,
        mode: this.mode,
      });
    },
  },
  watch: {
    display() {
      this.emitState();
    },
    running() {
      this.emitState();
    },
    mode() {
      this.emitState();
    },
  },
  mounted() {
    this.emitState();
  },
  beforeUnmount() {
    this.pause();
  },
};
</script>

<style scoped>
.pomodoro-card {
  background: #fff;
  border-radius: 12px;
  padding: 16px 20px;
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
  transition: border-color 0.3s;
  border-top: 4px solid #6c63ff;
}
.pomodoro-card.break {
  border-top-color: #22c55e;
}

.pomo-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 10px;
}
.pomo-title {
  font-size: 1.15rem;
  font-weight: 600;
  color: #2c3e50;
  margin: 0;
  display: flex;
  align-items: center;
  gap: 8px;
}
.pomo-title i {
  color: #6c63ff;
}
.pomodoro-card.break .pomo-title i {
  color: #22c55e;
}
.icon-btn {
  background: none;
  border: none;
  color: #adb5bd;
  cursor: pointer;
  font-size: 0.95rem;
  padding: 4px;
  transition: color 0.2s;
}
.icon-btn:hover {
  color: #6c63ff;
}

/* Settings */
.pomo-settings {
  display: flex;
  align-items: flex-end;
  gap: 10px;
  background: #f8f9fa;
  border-radius: 8px;
  padding: 12px;
  margin-bottom: 14px;
}
.setting {
  display: flex;
  flex-direction: column;
  gap: 4px;
  flex: 1;
}
.setting label {
  font-size: 0.72rem;
  color: #6c757d;
  font-weight: 500;
}
.setting input {
  width: 100%;
  border: 1px solid #dee2e6;
  border-radius: 6px;
  padding: 6px 8px;
  font-size: 0.9rem;
  font-family: inherit;
  outline: none;
}
.setting input:focus {
  border-color: #6c63ff;
}
.apply-btn {
  background: #6c63ff;
  color: #fff;
  border: none;
  border-radius: 6px;
  padding: 7px 12px;
  font-size: 0.82rem;
  font-weight: 600;
  cursor: pointer;
  font-family: inherit;
}
.apply-btn:hover {
  background: #5a52e0;
}

.mode-label {
  text-align: center;
  font-size: 0.85rem;
  font-weight: 600;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: #6c63ff;
  margin-bottom: 8px;
}
.pomodoro-card.break .mode-label {
  color: #22c55e;
}

/* Ring */
.ring-wrap {
  position: relative;
  width: 130px;
  height: 130px;
  margin: 0 auto 10px;
}
.ring {
  width: 100%;
  height: 100%;
  transform: rotate(-90deg);
}
.ring-track {
  fill: none;
  stroke: #eef0f5;
  stroke-width: 8;
}
.ring-progress {
  fill: none;
  stroke: #6c63ff;
  stroke-width: 8;
  stroke-linecap: round;
  transition: stroke-dashoffset 0.5s linear, stroke 0.3s;
}
.pomodoro-card.break .ring-progress {
  stroke: #22c55e;
}
.ring-time {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  font-size: 1.6rem;
  font-weight: 700;
  color: #2c3e50;
  font-variant-numeric: tabular-nums;
}

/* Controls */
.pomo-controls {
  display: flex;
  gap: 8px;
  justify-content: center;
  margin-bottom: 14px;
}
.ctrl-btn {
  border: none;
  border-radius: 8px;
  padding: 0.6rem 1rem;
  font-size: 0.9rem;
  font-weight: 600;
  cursor: pointer;
  font-family: inherit;
  display: flex;
  align-items: center;
  gap: 6px;
  transition: background 0.2s, transform 0.15s;
}
.ctrl-btn.primary {
  background: linear-gradient(135deg, #6c63ff, #4fc3f7);
  color: #fff;
  flex: 1;
  justify-content: center;
}
.pomodoro-card.break .ctrl-btn.primary {
  background: linear-gradient(135deg, #22c55e, #4ade80);
}
.ctrl-btn.primary:hover {
  transform: translateY(-1px);
}
.ctrl-btn.ghost {
  background: #f1f3f5;
  color: #495057;
}
.ctrl-btn.ghost:hover {
  background: #e9ecef;
}

.pomo-footer {
  text-align: center;
  font-size: 0.82rem;
  color: #6c757d;
}
.pomo-footer i {
  color: #22c55e;
}

/* Settings collapse transition */
.collapse-enter-active,
.collapse-leave-active {
  transition: opacity 0.2s ease;
}
.collapse-enter-from,
.collapse-leave-to {
  opacity: 0;
}
</style>
