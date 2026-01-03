// Arquivo: backend/server.js
const express = require('express');
const app = express();

/**
 * Define a porta do servidor backend.
 * @var number porta_backend
 */
const porta_backend = 3001; // Usamos 3001 para não conflitar com o front

/**
 * Rota de teste da API.
 * * @param object req
 * @param object res
 * @return void
 */
app.get('/', (req, res) => {
    let resposta_api = {
        status: 'sucesso',
        mensagem: 'Backend rodando perfeitamente!'
    };
    res.json(resposta_api);
});

app.listen(porta_backend, () => {
    console.log(`[BACKEND] Servidor rodando na porta ${porta_backend}`);
});