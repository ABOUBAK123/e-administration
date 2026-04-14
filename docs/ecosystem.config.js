module.exports = {
  apps: [{
    name: "e-administration.dyula.ci",
    script: "./dist/server.js", // ou "./index.js" si pas de build
    instances: "max",           // mode cluster (1 par CPU)
    exec_mode: "cluster",
    env_production: {
      NODE_ENV: "production",
      PORT: 3000
    }
  }]
};