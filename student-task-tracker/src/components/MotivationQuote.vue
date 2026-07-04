<template>
  <div class="quote-card">
    <div class="quote-header">
      <h3 class="quote-title">
        <i class="fas fa-lightbulb"></i> Daily Motivation
      </h3>
      <button
        class="icon-btn"
        title="New quote"
        :disabled="loading"
        @click="fetchQuote"
      >
        <i class="fas fa-sync-alt" :class="{ spin: loading }"></i>
      </button>
    </div>

    <transition name="fade" mode="out-in">
      <div v-if="loading" key="loading" class="quote-loading">
        <div class="dot"></div>
        <div class="dot"></div>
        <div class="dot"></div>
      </div>
      <blockquote v-else :key="quote.text" class="quote-body">
        <i class="fas fa-quote-left quote-mark"></i>
        <p class="quote-text">{{ quote.text }}</p>
        <footer class="quote-author">— {{ quote.author }}</footer>
      </blockquote>
    </transition>

    <div class="quote-note">
      <i class="fas fa-clock"></i> Refreshes every 5 minutes
    </div>
  </div>
</template>

<script>
// Used if the online API is unreachable, so the panel always shows something.
const FALLBACK_QUOTES = [
  { text: "The secret of getting ahead is getting started.", author: "Mark Twain" },
  { text: "Don't watch the clock; do what it does. Keep going.", author: "Sam Levenson" },
  { text: "Success is the sum of small efforts repeated day in and day out.", author: "Robert Collier" },
  { text: "It always seems impossible until it's done.", author: "Nelson Mandela" },
  { text: "Believe you can and you're halfway there.", author: "Theodore Roosevelt" },
  { text: "Quality is not an act, it is a habit.", author: "Aristotle" },
  { text: "The future depends on what you do today.", author: "Mahatma Gandhi" },
  { text: "Study while others are sleeping; work while others are loafing.", author: "William A. Ward" },
  { text: "Little by little, one travels far.", author: "J.R.R. Tolkien" },
  { text: "Discipline is the bridge between goals and accomplishment.", author: "Jim Rohn" },
];

const REFRESH_MS = 5 * 60 * 1000; // 5 minutes

export default {
  name: "MotivationQuote",
  data() {
    return {
      quote: { text: "", author: "" },
      loading: true,
      timerId: null,
    };
  },
  methods: {
    async fetchQuote() {
      this.loading = true;
      try {
        const res = await fetch("https://dummyjson.com/quotes/random");
        if (!res.ok) throw new Error("Bad response");
        const data = await res.json();
        this.quote = { text: data.quote, author: data.author || "Unknown" };
      } catch {
        // Network/CORS/API failure → show a random built-in quote instead
        this.quote =
          FALLBACK_QUOTES[Math.floor(Math.random() * FALLBACK_QUOTES.length)];
      } finally {
        this.loading = false;
      }
    },
  },
  mounted() {
    this.fetchQuote();
    this.timerId = window.setInterval(this.fetchQuote, REFRESH_MS);
  },
  beforeUnmount() {
    window.clearInterval(this.timerId);
  },
};
</script>

<style scoped>
.quote-card {
  background: linear-gradient(135deg, #6c63ff 0%, #4fc3f7 100%);
  border-radius: 12px;
  padding: 20px;
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
  color: #fff;
  display: flex;
  flex-direction: column;
}

.quote-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 14px;
}
.quote-title {
  font-size: 1.15rem;
  font-weight: 600;
  margin: 0;
  display: flex;
  align-items: center;
  gap: 8px;
}
.quote-title i {
  color: #ffe27a;
}
.icon-btn {
  background: rgba(255, 255, 255, 0.15);
  border: none;
  color: #fff;
  cursor: pointer;
  width: 30px;
  height: 30px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: background 0.2s;
}
.icon-btn:hover:not(:disabled) {
  background: rgba(255, 255, 255, 0.28);
}
.icon-btn:disabled {
  cursor: default;
  opacity: 0.7;
}
.spin {
  animation: spin 0.8s linear infinite;
}
@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

.quote-body {
  margin: 0;
  flex: 1;
  min-height: 90px;
}
.quote-mark {
  font-size: 1.2rem;
  opacity: 0.55;
  margin-bottom: 6px;
  display: block;
}
.quote-text {
  font-size: 1.02rem;
  line-height: 1.5;
  font-weight: 500;
  margin: 0 0 10px;
}
.quote-author {
  font-size: 0.88rem;
  font-style: italic;
  opacity: 0.9;
  text-align: right;
}

.quote-loading {
  display: flex;
  gap: 8px;
  justify-content: center;
  align-items: center;
  min-height: 90px;
}
.dot {
  width: 9px;
  height: 9px;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.8);
  animation: bounce 1s infinite ease-in-out;
}
.dot:nth-child(2) {
  animation-delay: 0.15s;
}
.dot:nth-child(3) {
  animation-delay: 0.3s;
}
@keyframes bounce {
  0%, 80%, 100% {
    transform: scale(0.6);
    opacity: 0.5;
  }
  40% {
    transform: scale(1);
    opacity: 1;
  }
}

.quote-note {
  margin-top: 14px;
  font-size: 0.75rem;
  opacity: 0.85;
  display: flex;
  align-items: center;
  gap: 5px;
}

.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.3s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
