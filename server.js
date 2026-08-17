// Servidor estático mínimo para Railway — sin dependencias externas.
// Sirve únicamente index.html (single-file app, sin build). Todo lo demás
// (Supabase, lógica de negocio) vive en el propio index.html.
const http = require('http');
const fs = require('fs');
const path = require('path');

const PORT = process.env.PORT || 3000;
const INDEX_PATH = path.join(__dirname, 'index.html');

const server = http.createServer((req, res) => {
  const url = req.url.split('?')[0];
  if (url === '/' || url === '/index.html') {
    fs.readFile(INDEX_PATH, (err, data) => {
      if (err) {
        res.writeHead(500, { 'Content-Type': 'text/plain; charset=utf-8' });
        res.end('No se pudo leer index.html');
        return;
      }
      res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
      res.end(data);
    });
    return;
  }
  res.writeHead(404, { 'Content-Type': 'text/plain; charset=utf-8' });
  res.end('No encontrado');
});

server.listen(PORT, () => {
  console.log(`Análisis de Cierre escuchando en el puerto ${PORT}`);
});
