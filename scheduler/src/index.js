const express = require('express');
const cors = require('cors');
const { getDb } = require('./db');
const { authMiddleware } = require('./auth');
const appointments = require('./routes/appointments');
const customers = require('./routes/customers');
const services = require('./routes/services');
const providers = require('./routes/providers');
const slots = require('./routes/slots');
const whatsapp = require('./routes/whatsapp');

const app = express();
const PORT = process.env.PORT || 3000;

app.disable('x-powered-by');
app.use(cors({ origin: process.env.CORS_ORIGIN || 'http://localhost:8080' }));
app.use(express.json());

app.use(authMiddleware);

const api = express.Router();
app.use('/api/v1', api);

appointments.register(api);
customers.register(api);
services.register(api);
providers.register(api);
slots.register(api);
whatsapp.register(api);

app.get('/health', (req, res) => {
  res.json({ status: 'ok' });
});

getDb();
app.listen(PORT, () => {
  console.log(`tetoca-scheduler running on port ${PORT}`);
});
