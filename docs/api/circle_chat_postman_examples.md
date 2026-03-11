# Circle Chat API (Postman Examples)

Base URL: `{{base_url}}/api/v1`

Headers:
- `Authorization: Bearer {{token}}`
- `Accept: application/json`

## 1) Get circle chat messages

**GET** `{{base_url}}/api/v1/circles/{{circle_id}}/chat/messages?per_page=20&include_reads=true`

## 2) Send text message

**POST** `{{base_url}}/api/v1/circles/{{circle_id}}/chat/messages`

Body (raw JSON):
```json
{
  "message_type": "text",
  "message_text": "Hello Winners"
}
```

## 3) Send image message

**POST** `{{base_url}}/api/v1/circles/{{circle_id}}/chat/messages`

Body (form-data):
- `message_type`: `image`
- `attachment`: `<select image file>`
- `reply_to_message_id` (optional): `<uuid>`

## 4) Send video message

**POST** `{{base_url}}/api/v1/circles/{{circle_id}}/chat/messages`

Body (form-data):
- `message_type`: `video`
- `attachment`: `<select video file>`

## 5) Mark messages read

**POST** `{{base_url}}/api/v1/circles/{{circle_id}}/chat/messages/read`

Body (raw JSON):
```json
{
  "message_ids": [
    "{{message_id_1}}",
    "{{message_id_2}}"
  ]
}
```

## 6) Get message read details

**GET** `{{base_url}}/api/v1/circles/{{circle_id}}/chat/messages/{{message_id}}/reads`

## 7) Delete from me

**POST** `{{base_url}}/api/v1/circles/{{circle_id}}/chat/messages/{{message_id}}/delete-for-me`

## 8) Delete from all

**DELETE** `{{base_url}}/api/v1/circles/{{circle_id}}/chat/messages/{{message_id}}`

---

## Artisan commands used for setup

```bash
php artisan make:model CircleChatMessage -m
php artisan make:model CircleChatMessageRead -m
php artisan make:event CircleChatMessageSent
php artisan make:event CircleChatMessageDeletedForAll
php artisan make:event CircleChatMessagesRead
php artisan make:controller Api/CircleChatController
php artisan make:request CircleChat/SendCircleChatMessageRequest
php artisan make:request CircleChat/MarkCircleChatMessagesReadRequest
php artisan make:resource CircleChatMessageResource

php artisan migrate
php artisan reverb:start
php artisan queue:work
php artisan optimize:clear
```

## Integration notes

- Circle membership access is restricted to `circle_members.status = approved` and non-deleted membership rows.
- Circle itself is treated as the chat container, so no extra `circle_chats` table is required.
- Attachments are stored in `files` and served via route-based URL (`/api/v1/files/{id}`).
- Notification row type uses `new_message` (existing enum), with payload `type = circle_chat_message` for frontend routing.
