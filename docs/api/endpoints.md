# API Endpoints - FlowSent Webmail Client

**Base URL:** `http://127.0.0.1:8000/api`

Semua endpoint (kecuali login) membutuhkan **Bearer Token** di header:

```http
Authorization: Bearer <token>
```

---

## Authentication

### Login

**POST** `/login`

**Body:**

```json
{
  "email": "user@example.com",
  "password": "secret"
}
```

**Response:**

```json
{
  "token": "JWT_TOKEN",
  "user": { "email": "user@example.com" }
}
```

### Logout

**POST** `/logout`

**Response:**

```json
{ "message": "Logout success" }
```

---

## Emails

### Fetch All Emails

**GET** `/emails/all?refresh=true|false`

**Query Params:**

- `refresh` (opsional) → `true` untuk force refresh dari server

**Response:**  
Object berisi list email per folder (inbox, sent, draft, deleted, dll).

### Send Email

**POST** `/emails/send`

**Body (FormData):**

- `to`: string (required)
- `subject`: string (required)
- `body`: string (required)
- `attachments[]`: file(s) (opsional)
- `draft_id`: string (opsional, jika kirim dari draft)
- `stored_attachments`: array JSON (opsional)
- `message_id`: string (opsional, untuk reply/forward)

**Response:**

```json
{ "message": "Email sent successfully" }
```

### Save Draft

**POST** `/emails/draft`

**Body (FormData):**

- `to`, `subject`, `body`, `attachments[]`

**Response:**

```json
{ "message": "Draft saved successfully" }
```

---

## Attachments

### Download Attachment

**GET** `/emails/attachments/{uid}/download/{filename}`

**Response:** file download

### Preview Attachment

**GET** `/emails/attachments/{uid}/preview/{filename}`

**Mendukung:** jpg, jpeg, png, gif, webp, pdf, txt

**Response:** Blob file (previewable)

---

## Email Actions

### Mark as Read

**POST** `/emails/mark-as-read`

**Body:**

```json
{
  "folder": "inbox",
  "message_id": "123"
}
```

### Flag Email

**POST** `/emails/flag`

**Body:**

```json
{
  "folder": "inbox",
  "message_id": "123"
}
```

### Unflag Email

**POST** `/emails/unflag`

**Body:**

```json
{
  "folder": "inbox",
  "message_id": "123"
}
```

### Move Email

**POST** `/emails/move`

**Body:**

```json
{
  "folder": "inbox",
  "message_ids": ["123", "124"],
  "target_folder": "archived"
}
```

---

## Delete

### Delete Permanent All (Empty Trash)

**DELETE** `/emails/delete-permanent-all`

**Response:**

```json
{ "message": "All emails permanently deleted" }
```

### Delete Permanent Selected

**DELETE** `/emails/deletePermanent`

**Body:**

```json
{
  "messageIds": ["123", "124"]
}
```

**Response:**

```json
{ "message": "Selected emails permanently deleted" }
```
