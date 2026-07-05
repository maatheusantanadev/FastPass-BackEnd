# FastPass — Backend (MVP)

API REST em **Laravel 11** para a plataforma FastPass: venda de passagens, gestão de excursões e embarque inteligente por reconhecimento facial ou QR Code. Projeto desenvolvido como MVP para apresentação de TCC.

## Arquitetura

- **Laravel 11** (API stateless, autenticação por token com Sanctum)
- **PostgreSQL (Supabase)** como banco de dados
- **API externa de reconhecimento facial** (FastAPI + DeepFace), consumida via HTTP
- **Docker** para o ambiente de execução

```
Cliente (app/web) ──> FastPass API (Laravel) ──> Supabase (PostgreSQL)
                              │
                              └──> API Facial (FastAPI + DeepFace)
```

## Como executar

1. Copie o arquivo de exemplo e preencha as credenciais:

```bash
cp .env.example .env
```

Edite o `.env` com o host, usuário e **senha do Supabase** e a URL da API facial (`FACIAL_API_URL`). O `.env` está no `.gitignore` e **não deve ser versionado**.

> **Nota sobre o Supabase:** a conexão direta (`db.<ref>.supabase.co:5432`) exige IPv6. Se a rede não tiver suporte, use o **Session Pooler** (host `aws-0-<região>.pooler.supabase.com`, porta `5432`, usuário `postgres.<ref>`), disponível em *Settings → Database* no painel do Supabase.

2. Suba o container:

```bash
docker compose up --build
```

Na primeira execução o entrypoint instala as dependências do Composer e gera a `APP_KEY` automaticamente.

3. Rode as migrations e o seeder (em outro terminal):

```bash
docker compose exec app php artisan migrate --seed
```

A API estará disponível em `http://localhost:8000`.

> **CORS (front-end):** a origem da SPA é liberada em `config/cors.php`. Em desenvolvimento o Vite roda em `http://localhost:5173` (já incluído); em produção defina `FRONTEND_URL` no `.env`. A autenticação usa tokens Bearer, então não há troca de cookies.

O seeder popula as **cinco excursões da Bahia** exibidas no app (Praia do Forte, Chapada Diamantina, Morro de São Paulo, Cachoeira & Recôncavo e Ilha dos Frades), já com `categoria`, `cena`, `empresa` e pontos de partida/retorno — de modo que a API entregue exatamente o catálogo esperado pelo front-end.

## Fluxo do sistema (TCC)

| # | Etapa | Endpoint |
|---|-------|----------|
| 1 | Cadastro | `POST /api/auth/register` |
| 2 | Login | `POST /api/auth/login` |
| 3 | Dashboard com excursões disponíveis | `GET /api/excursoes` |
| 4 | Compra efetuada | `POST /api/compras` |
| 5 | Registro da facial na compra | `POST /api/compras/{id}/facial` |
| 6 | Validação de facial (embarque) | `POST /api/embarque/facial` |
| 6b | Embarque alternativo por QR Code | `POST /api/embarque/qrcode` |
| 7 | Viagem concluída | `POST /api/excursoes/{id}/concluir` |

Rotas autenticadas exigem o header `Authorization: Bearer <token>` e `Accept: application/json`.

## Exemplos de requisição

**1. Cadastro**
```bash
curl -X POST http://localhost:8000/api/auth/register \
  -H "Content-Type: application/json" -H "Accept: application/json" \
  -d '{"name":"Maria Silva","email":"maria@exemplo.com","password":"senha1234","password_confirmation":"senha1234"}'
```

**2. Login**
```bash
curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" -H "Accept: application/json" \
  -d '{"email":"maria@exemplo.com","password":"senha1234"}'
```

**3. Excursões disponíveis**
```bash
curl http://localhost:8000/api/excursoes \
  -H "Authorization: Bearer SEU_TOKEN" -H "Accept: application/json"
```

**4. Comprar passagem**
```bash
curl -X POST http://localhost:8000/api/compras \
  -H "Authorization: Bearer SEU_TOKEN" \
  -H "Content-Type: application/json" -H "Accept: application/json" \
  -d '{"excursao_id":1}'
```
A resposta inclui o `codigo_qr` (UUID) da passagem — alternativa de embarque ao reconhecimento facial.

**5. Registrar a facial na compra**
```bash
curl -X POST http://localhost:8000/api/compras/1/facial \
  -H "Authorization: Bearer SEU_TOKEN" \
  -H "Content-Type: application/json" -H "Accept: application/json" \
  -d '{"imagem":"<BASE64_DA_FOTO>"}'
```

**6. Validar facial no embarque**
```bash
curl -X POST http://localhost:8000/api/embarque/facial \
  -H "Authorization: Bearer SEU_TOKEN" \
  -H "Content-Type: application/json" -H "Accept: application/json" \
  -d '{"excursao_id":1,"imagem":"<BASE64_DA_FOTO>"}'
```

**6b. Embarque por QR Code**
```bash
curl -X POST http://localhost:8000/api/embarque/qrcode \
  -H "Authorization: Bearer SEU_TOKEN" \
  -H "Content-Type: application/json" -H "Accept: application/json" \
  -d '{"codigo_qr":"<UUID_DA_COMPRA>"}'
```

**7. Concluir a viagem**
```bash
curl -X POST http://localhost:8000/api/excursoes/1/concluir \
  -H "Authorization: Bearer SEU_TOKEN" -H "Accept: application/json"
```

**Painel de gestão da excursão (visão da empresa)**
```bash
curl http://localhost:8000/api/excursoes/1/painel \
  -H "Authorization: Bearer SEU_TOKEN" -H "Accept: application/json"
```
Retorna vagas, passageiros confirmados, embarcados, ocupação percentual e a lista de embarque em tempo real.

## Reconhecimento facial (microserviço FastPass-Facial)

A biometria fica num serviço dedicado — o **FastPass-Facial** (FastAPI + DeepFace) — que guarda o embedding ligado ao `id` do usuário. O Laravel é o **único cliente** dele (o front nunca o chama diretamente) e atua como proxy, autenticando via `Authorization: Bearer FACIAL_API_KEY`. A imagem é enviada como arquivo (multipart).

O `FacialRecognitionService` consome (base em `FACIAL_API_URL`):

- `POST /faces` — multipart `fastpass_user_id`, `nome?`, `file` → `{ "facial_id": "..." }`
- `POST /identify` — multipart `file`, `candidatos?` → `{ "match": true, "fastpass_user_id": 1, "confianca": 85.5 }`

No embarque, o Laravel envia em `candidatos` os ids dos passageiros com biometria registrada na excursão, restringindo a busca. Configure `FACIAL_API_URL` (URL do serviço/Space) e a **mesma** `FACIAL_API_KEY` nos dois lados.

> O repositório do serviço fica em `../FastPass-Facial` (deploy no Hugging Face Spaces, porta 7860). Os embeddings vão numa tabela `faces` no **mesmo Supabase** deste backend.

### Modo simulado (demonstração)

Com `FACIAL_API_FAKE=true` no `.env`, a API facial é simulada:

- O **registro** sempre retorna sucesso com um `facial_id` fictício.
- Na **validação**, envie `"imagem": "user:<id>"` (ex.: `"user:1"`) para simular o reconhecimento do usuário de id 1.

Isso permite demonstrar o fluxo completo do TCC mesmo sem a API DeepFace em execução.

## Modelo de dados

- **users** — passageiros (nome, e-mail, CPF, telefone, senha)
- **excursoes** — título, destino, datas, preço, vagas totais/disponíveis, status (`aberta` → `encerrada`/`concluida`) e campos de apresentação consumidos pelo app: `categoria` (`praia`/`aventura`), `cena` (ilustração do card: `praia`/`montanha`/`ilha`), `empresa`, `ponto_partida` e `ponto_retorno`
- **compras** — vínculo usuário × excursão, `codigo_qr` único, valor, biometria (`facial_registrada`, `facial_id`), `embarcado_em`, status (`confirmada` → `embarcada` → `concluida`)

A compra decrementa `vagas_disponiveis` dentro de uma transação com `lockForUpdate`, evitando overbooking em compras concorrentes.

## Segurança

- Senhas com hash bcrypt (cast `hashed` do Laravel)
- Autenticação por token (Sanctum), com revogação no logout
- Credenciais exclusivamente via variáveis de ambiente — o `.env` nunca é versionado
- Conexão ao Supabase com `sslmode=require`
