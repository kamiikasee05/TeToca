function authMiddleware(req, res, next) {
  const publicRoutes = [
    { method: 'GET', pattern: /^\/health$/ },
    { method: 'GET', pattern: /^\/api\/v1\/services\/?(\d+)?$/ },
    { method: 'GET', pattern: /^\/api\/v1\/slots/ },
    { method: 'GET', pattern: /^\/api\/v1\/availabilities/ },
    { method: 'POST', pattern: /^\/api\/v1\/customers$/ },
    { method: 'POST', pattern: /^\/api\/v1\/appointments$/ },
  ];

  const isPublic = publicRoutes.some(r =>
    r.method === req.method && r.pattern.test(req.path)
  );
  if (isPublic) return next();

  const expected = process.env.API_KEY;

  const apiKey = req.headers['x-api-key'];
  const match = apiKey && apiKey === expected;
  if (process.env.NODE_ENV !== 'production') console.log(`[auth] path=${req.path} method=${req.method} match=${match}`);
  if (match) return next();

  const auth = req.headers['authorization'];
  if (auth && auth.startsWith('Basic ')) {
    const decoded = Buffer.from(auth.slice(6), 'base64').toString();
    const [user, pass] = decoded.split(':');
    if (user && pass === expected) return next();
  }

  return res.status(401).json({ success: false, message: 'API key inválida o faltante' });
}

module.exports = { authMiddleware };
