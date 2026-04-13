/**
 * PM2 Ecosystem Configuration
 * 
 * Usage:
 *   pm2 start ecosystem.config.js          # Start all apps
 *   pm2 restart ecosystem.config.js        # Restart all apps
 *   pm2 stop ecosystem.config.js           # Stop all apps
 *   pm2 delete all                         # Remove from PM2
 *   pm2 logs                               # View logs
 *   pm2 monit                              # Dashboard
 */

module.exports = {
  apps: [
    {
      // ============================================
      // Backend NestJS Application
      // ============================================
      name: "e-admin-backend",
      script: "./apps/backend/dist/main.js",
      
      // Cluster mode: run multiple instances for better performance
      instances: 2,
      exec_mode: "cluster",
      
      // Files to watch for reload (disabled in production)
      watch: false,
      
      // Max memory before restart (prevent memory leaks)
      max_memory_restart: "1G",
      
      // Node args (e.g., --max-old-space-size)
      node_args: "--max-old-space-size=512",
      
      // Delay between restart attempts
      restart_delay: 4000,
      
      // Listen for SIGINT and exit with 0
      kill_timeout: 5000,
      wait_ready: true,
      
      // Logging
      error_file: "./logs/backend-error.log",
      out_file: "./logs/backend-out.log",
      log_date_format: "YYYY-MM-DD HH:mm:ss Z",
      
      // Environment variables
      env: {
        NODE_ENV: "production",
        API_PORT: 3000,
        API_HOST: "0.0.0.0",
      },
      
      // Environment for development
      env_development: {
        NODE_ENV: "development",
        API_PORT: 3000,
      },
      
      // Merge logs from multiple instances
      merge_logs: true,
      
      // Ignore watch patterns
      ignore_watch: ["node_modules", "dist", "logs", ".git"],
    },

    {
      // ============================================
      // Frontend Vite Preview
      // ============================================
      name: "e-admin-frontend",
      script: "npm",
      args: "run preview -- --host 0.0.0.0 --port 5173",
      
      // Run in default (non-cluster) mode
      instances: 1,
      exec_mode: "fork",
      
      // Change to frontend directory
      cwd: "./apps/frontend",
      
      // Do NOT watch files (build happens offline)
      watch: false,
      
      // Memory limit
      max_memory_restart: "512M",
      
      // Logging
      error_file: "../logs/frontend-error.log",
      out_file: "../logs/frontend-out.log",
      log_date_format: "YYYY-MM-DD HH:mm:ss Z",
      
      // Environment
      env: {
        NODE_ENV: "production",
      },
      
      // Kill timeout (Vite preview may take time to shutdown)
      kill_timeout: 8000,
    },
  ],

  // ========================================
  // Deploy Configuration (optional)
  // ========================================
  // This section is for deploying via PM2 Deploy
  deploy: {
    production: {
      user: "eadmin",
      host: "your-server.com",
      ref: "origin/main",
      repo: "https://github.com/your-repo/e-administration.git",
      path: "/var/www/e-administration",
      "post-deploy": "npm install && npm run build && pm2 reload ecosystem.config.js --env production",
    },
    staging: {
      user: "eadmin",
      host: "staging-server.com",
      ref: "origin/develop",
      repo: "https://github.com/your-repo/e-administration.git",
      path: "/var/www/e-administration-staging",
      "post-deploy": "npm install && npm run build && pm2 reload ecosystem.config.js --env staging",
    },
  },
};
