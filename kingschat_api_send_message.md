# KingsChat API: Sending Messages

This document describes how to send messages from one user to another using the KingsChat API.

## Prerequisites

Before you can send messages via the API, you need:

1. A valid KingsChat user account
2. An access token with the appropriate permissions (specifically the `send_chat_message` scope)

## Authentication

All API requests require authentication using a Bearer token. This token should be included in the `Authorization` header of your request.

```
Authorization: Bearer <your_access_token>
```

## Sending a Message

### Endpoint

To send a message to another user, use the following endpoint:

```
POST https://connect.kingsch.at/api/users/{recipient_id}/new_message
```

Where `{recipient_id}` is the ID of the user who will receive the message.

### Headers

- `Content-Type: application/json`
- `Authorization: Bearer <your_access_token>`

### Request Body

The request body should be a JSON object with the following structure:

```json
{
  "message": {
    "body": {
      "text": {
        "body": "Your message content goes here"
      }
    }
  }
}
```

### Example Request (using curl)

```bash
curl -X POST "https://connect.kingsch.at/api/users/{recipient_id}/new_message" \
  -H "Authorization: Bearer <your_access_token>" \
  -H "Content-Type: application/json" \
  -d '{"message": {"body": {"text": {"body": "Hello, this is a message sent via the API"}}}}'
```

### Response

A successful request will return a `200 OK` status code with a minimal JSON response.

## Notes

- The sender is identified by the user associated with the access token
- The token must have the `send_chat_message` scope
- Messages are delivered in real-time to the recipient if they are online
- The API follows RESTful principles

## Troubleshooting

- `401 Unauthorized`: Check that your access token is valid and has not expired
- `404 Not Found`: Verify that the recipient ID is correct
- `400 Bad Request`: Ensure your request body is properly formatted

## Security Considerations

- Never share your access token
- Use HTTPS for all API requests
- Tokens have an expiration time for security reasons
- Implement proper error handling in your application




The default interactive shell is now zsh.
To update your account to use zsh, please run `chsh -s /bin/zsh`.
Mayowas-MacBook-Pro:kingschat maxwellvn$ curl -X POST "https://connect.kingsch.at/api/users/5d03686cde867f0001b9df3d/new_message" \
> -H "Authorization: Bearer eyJhbGciOiJSUzI1NiIsImtpZCI6IjkzZjFkY2FjLTg5MjQtNDU3MS04ZWE5LWI3Mjc4MzY5Yzc5YiIsInR5cCI6IkpXVCJ9.eyJleHAiOjE3NDM1ODI5OTIxMDUsInN1YiI6IjY3YzZkNDg2MGIyMDk3NzAzNTg2NWY5OCIsImFsZyI6IlJTMjU2IiwiaXNzIjoia2luZ3NjaGF0IiwiYXVkIjpbInVzZXIiLCJwcm9maWxlIiwic2VuZF9jaGF0X21lc3NhZ2UiXSwiYWNpZCI6ImNvbS5raW5nc2NoYXQiLCJjaWQiOiI2N2VjZTkwMDAxNzNiYWI2NGFkMDNlNmUifQ.B40ZxKBNivMoiRGV_SKaRQFuSSgD5Yl6H_7yHSCd3dQH1GOBXEx_SB0F-G_7kSHCuSAV7oLC5qyg6oGPCRQtKvpkjZqm8hlxpQ-GOI46mwRa4oc1_KO6eUCbtGqFWRTE9QH_Bav1M7qjgRkzDAwxXHpZL3NY3rxyKDdML1-bcdvlnka88cbKek3leS-inI2SbI5i4YrSJueXZ-T9eJAZoqCRGJKqlT5n31wZGfb-6YWZz1csU79THDspFcr83WMObrxGFrptfwS8N2BdmNuq7fK1ltm0yAUbjy7UWgZsyRiKUPqshxql6UfKUi4X5hz4edLWykQwsucpg1-Qbx3yZw" \
> -H "Content-Type: application/json" \
> -d '{"message": {"body": {"text": {"body": "Hello, this is a test message from the API"}}}}'
Mayowas-MacBook-Pro:kingschat maxwellvn$ :
Mayowas-MacBook-Pro:kingschat maxwellvn$ curl -v -X POST "https://connect.kingsch.at/api/users/5d03686cde867f0001b9df3d/new_message" \
> -H "Authorization: Bearer eyJhbGciOiJSUzI1NiIsImtpZCI6IjkzZjFkY2FjLTg5MjQtNDU3MS04ZWE5LWI3Mjc4MzY5Yzc5YiIsInR5cCI6IkpXVCJ9.eyJleHAiOjE3NDM1ODI5OTIxMDUsInN1YiI6IjY3YzZkNDg2MGIyMDk3NzAzNTg2NWY5OCIsImFsZyI6IlJTMjU2IiwiaXNzIjoia2luZ3NjaGF0IiwiYXVkIjpbInVzZXIiLCJwcm9maWxlIiwic2VuZF9jaGF0X21lc3NhZ2UiXSwiYWNpZCI6ImNvbS5raW5nc2NoYXQiLCJjaWQiOiI2N2VjZTkwMDAxNzNiYWI2NGFkMDNlNmUifQ.B40ZxKBNivMoiRGV_SKaRQFuSSgD5Yl6H_7yHSCd3dQH1GOBXEx_SB0F-G_7kSHCuSAV7oLC5qyg6oGPCRQtKvpkjZqm8hlxpQ-GOI46mwRa4oc1_KO6eUCbtGqFWRTE9QH_Bav1M7qjgRkzDAwxXHpZL3NY3rxyKDdML1-bcdvlnka88cbKek3leS-inI2SbI5i4YrSJueXZ-T9eJAZoqCRGJKqlT5n31wZGfb-6YWZz1csU79THDspFcr83WMObrxGFrptfwS8N2BdmNuq7fK1ltm0yAUbjy7UWgZsyRiKUPqshxql6UfKUi4X5hz4edLWykQwsucpg1-Qbx3yZw" \
> -H "Content-Type: application/json" \
> -d '{"message": {"body": {"text": {"body": "Hello, this is a test message from the API"}}}}'
Note: Unnecessary use of -X or --request, POST is already inferred.
* Host connect.kingsch.at:443 was resolved.
* IPv6: (none)
* IPv4: 54.75.169.230, 54.75.179.82, 54.195.114.156
*   Trying 54.75.169.230:443...
* Connected to connect.kingsch.at (54.75.169.230) port 443
* ALPN: curl offers h2,http/1.1
* (304) (OUT), TLS handshake, Client hello (1):
*  CAfile: /etc/ssl/cert.pem
*  CApath: none
* (304) (IN), TLS handshake, Server hello (2):
* (304) (IN), TLS handshake, Unknown (8):
* (304) (IN), TLS handshake, Certificate (11):
* (304) (IN), TLS handshake, CERT verify (15):
* (304) (IN), TLS handshake, Finished (20):
* (304) (OUT), TLS handshake, Finished (20):
* SSL connection using TLSv1.3 / AEAD-AES256-GCM-SHA384 / [blank] / UNDEF
* ALPN: server accepted h2
* Server certificate:
*  subject: CN=kingsch.at
*  start date: Apr  3 02:08:36 2025 GMT
*  expire date: Jul  2 02:08:35 2025 GMT
*  subjectAltName: host "connect.kingsch.at" matched cert's "connect.kingsch.at"
*  issuer: C=US; O=Let's Encrypt; CN=R10
*  SSL certificate verify ok.
* using HTTP/2
* [HTTP/2] [1] OPENED stream for https://connect.kingsch.at/api/users/5d03686cde867f0001b9df3d/new_message
* [HTTP/2] [1] [:method: POST]
* [HTTP/2] [1] [:scheme: https]
* [HTTP/2] [1] [:authority: connect.kingsch.at]
* [HTTP/2] [1] [:path: /api/users/5d03686cde867f0001b9df3d/new_message]
* [HTTP/2] [1] [user-agent: curl/8.7.1]
* [HTTP/2] [1] [accept: */*]
* [HTTP/2] [1] [authorization: Bearer eyJhbGciOiJSUzI1NiIsImtpZCI6IjkzZjFkY2FjLTg5MjQtNDU3MS04ZWE5LWI3Mjc4MzY5Yzc5YiIsInR5cCI6IkpXVCJ9.eyJleHAiOjE3NDM1ODI5OTIxMDUsInN1YiI6IjY3YzZkNDg2MGIyMDk3NzAzNTg2NWY5OCIsImFsZyI6IlJTMjU2IiwiaXNzIjoia2luZ3NjaGF0IiwiYXVkIjpbInVzZXIiLCJwcm9maWxlIiwic2VuZF9jaGF0X21lc3NhZ2UiXSwiYWNpZCI6ImNvbS5raW5nc2NoYXQiLCJjaWQiOiI2N2VjZTkwMDAxNzNiYWI2NGFkMDNlNmUifQ.B40ZxKBNivMoiRGV_SKaRQFuSSgD5Yl6H_7yHSCd3dQH1GOBXEx_SB0F-G_7kSHCuSAV7oLC5qyg6oGPCRQtKvpkjZqm8hlxpQ-GOI46mwRa4oc1_KO6eUCbtGqFWRTE9QH_Bav1M7qjgRkzDAwxXHpZL3NY3rxyKDdML1-bcdvlnka88cbKek3leS-inI2SbI5i4YrSJueXZ-T9eJAZoqCRGJKqlT5n31wZGfb-6YWZz1csU79THDspFcr83WMObrxGFrptfwS8N2BdmNuq7fK1ltm0yAUbjy7UWgZsyRiKUPqshxql6UfKUi4X5hz4edLWykQwsucpg1-Qbx3yZw]
* [HTTP/2] [1] [content-type: application/json]
* [HTTP/2] [1] [content-length: 87]
> POST /api/users/5d03686cde867f0001b9df3d/new_message HTTP/2
> Host: connect.kingsch.at
> User-Agent: curl/8.7.1
> Accept: */*
> Authorization: Bearer eyJhbGciOiJSUzI1NiIsImtpZCI6IjkzZjFkY2FjLTg5MjQtNDU3MS04ZWE5LWI3Mjc4MzY5Yzc5YiIsInR5cCI6IkpXVCJ9.eyJleHAiOjE3NDM1ODI5OTIxMDUsInN1YiI6IjY3YzZkNDg2MGIyMDk3NzAzNTg2NWY5OCIsImFsZyI6IlJTMjU2IiwiaXNzIjoia2luZ3NjaGF0IiwiYXVkIjpbInVzZXIiLCJwcm9maWxlIiwic2VuZF9jaGF0X21lc3NhZ2UiXSwiYWNpZCI6ImNvbS5raW5nc2NoYXQiLCJjaWQiOiI2N2VjZTkwMDAxNzNiYWI2NGFkMDNlNmUifQ.B40ZxKBNivMoiRGV_SKaRQFuSSgD5Yl6H_7yHSCd3dQH1GOBXEx_SB0F-G_7kSHCuSAV7oLC5qyg6oGPCRQtKvpkjZqm8hlxpQ-GOI46mwRa4oc1_KO6eUCbtGqFWRTE9QH_Bav1M7qjgRkzDAwxXHpZL3NY3rxyKDdML1-bcdvlnka88cbKek3leS-inI2SbI5i4YrSJueXZ-T9eJAZoqCRGJKqlT5n31wZGfb-6YWZz1csU79THDspFcr83WMObrxGFrptfwS8N2BdmNuq7fK1ltm0yAUbjy7UWgZsyRiKUPqshxql6UfKUi4X5hz4edLWykQwsucpg1-Qbx3yZw
> Content-Type: application/json
> Content-Length: 87
> 
* upload completely sent off: 87 bytes
< HTTP/2 401 
< date: Wed, 30 Apr 2025 07:15:02 GMT
< content-length: 0
< access-control-allow-credentials: true
< access-control-allow-origin: *
< access-control-expose-headers: 
< cache-control: max-age=0, private, must-revalidate
< x-request-id: afdb18edf2e4a72b2e8c64ed1884f61b
< strict-transport-security: max-age=15724800; includeSubDomains
< 
* Connection #0 to host connect.kingsch.at left intact
Mayowas-MacBook-Pro:kingschat maxwellvn$ :
Mayowas-MacBook-Pro:kingschat maxwellvn$ curl -v -X POST "https://connect.kingsch.at/api/v1/users/5d03686cde867f0001b9df3d/new_message" \
> -H "Authorization: Bearer eyJhbGciOiJSUzI1NiIsImtpZCI6IjkzZjFkY2FjLTg5MjQtNDU3MS04ZWE5LWI3Mjc4MzY5Yzc5YiIsInR5cCI6IkpXVCJ9.eyJleHAiOjE3NDM1ODI5OTIxMDUsInN1YiI6IjY3YzZkNDg2MGIyMDk3NzAzNTg2NWY5OCIsImFsZyI6IlJTMjU2IiwiaXNzIjoia2luZ3NjaGF0IiwiYXVkIjpbInVzZXIiLCJwcm9maWxlIiwic2VuZF9jaGF0X21lc3NhZ2UiXSwiYWNpZCI6ImNvbS5raW5nc2NoYXQiLCJjaWQiOiI2N2VjZTkwMDAxNzNiYWI2NGFkMDNlNmUifQ.B40ZxKBNivMoiRGV_SKaRQFuSSgD5Yl6H_7yHSCd3dQH1GOBXEx_SB0F-G_7kSHCuSAV7oLC5qyg6oGPCRQtKvpkjZqm8hlxpQ-GOI46mwRa4oc1_KO6eUCbtGqFWRTE9QH_Bav1M7qjgRkzDAwxXHpZL3NY3rxyKDdML1-bcdvlnka88cbKek3leS-inI2SbI5i4YrSJueXZ-T9eJAZoqCRGJKqlT5n31wZGfb-6YWZz1csU79THDspFcr83WMObrxGFrptfwS8N2BdmNuq7fK1ltm0yAUbjy7UWgZsyRiKUPqshxql6UfKUi4X5hz4edLWykQwsucpg1-Qbx3yZw" \
> -H "Content-Type: application/json" \
> -d '{"message": {"body": {"text": {"body": "Hello, this is a test message from the API"}}}}'
Note: Unnecessary use of -X or --request, POST is already inferred.
* Host connect.kingsch.at:443 was resolved.
* IPv6: (none)
* IPv4: 54.75.179.82, 54.195.114.156, 54.75.169.230
*   Trying 54.75.179.82:443...
* Connected to connect.kingsch.at (54.75.179.82) port 443
* ALPN: curl offers h2,http/1.1
* (304) (OUT), TLS handshake, Client hello (1):
*  CAfile: /etc/ssl/cert.pem
*  CApath: none
* (304) (IN), TLS handshake, Server hello (2):
* (304) (IN), TLS handshake, Unknown (8):
* (304) (IN), TLS handshake, Certificate (11):
* (304) (IN), TLS handshake, CERT verify (15):
* (304) (IN), TLS handshake, Finished (20):
* (304) (OUT), TLS handshake, Finished (20):
* SSL connection using TLSv1.3 / AEAD-AES256-GCM-SHA384 / [blank] / UNDEF
* ALPN: server accepted h2
* Server certificate:
*  subject: CN=kingsch.at
*  start date: Apr  3 02:08:36 2025 GMT
*  expire date: Jul  2 02:08:35 2025 GMT
*  subjectAltName: host "connect.kingsch.at" matched cert's "connect.kingsch.at"
*  issuer: C=US; O=Let's Encrypt; CN=R10
*  SSL certificate verify ok.
* using HTTP/2
* [HTTP/2] [1] OPENED stream for https://connect.kingsch.at/api/v1/users/5d03686cde867f0001b9df3d/new_message
* [HTTP/2] [1] [:method: POST]
* [HTTP/2] [1] [:scheme: https]
* [HTTP/2] [1] [:authority: connect.kingsch.at]
* [HTTP/2] [1] [:path: /api/v1/users/5d03686cde867f0001b9df3d/new_message]
* [HTTP/2] [1] [user-agent: curl/8.7.1]
* [HTTP/2] [1] [accept: */*]
* [HTTP/2] [1] [authorization: Bearer eyJhbGciOiJSUzI1NiIsImtpZCI6IjkzZjFkY2FjLTg5MjQtNDU3MS04ZWE5LWI3Mjc4MzY5Yzc5YiIsInR5cCI6IkpXVCJ9.eyJleHAiOjE3NDM1ODI5OTIxMDUsInN1YiI6IjY3YzZkNDg2MGIyMDk3NzAzNTg2NWY5OCIsImFsZyI6IlJTMjU2IiwiaXNzIjoia2luZ3NjaGF0IiwiYXVkIjpbInVzZXIiLCJwcm9maWxlIiwic2VuZF9jaGF0X21lc3NhZ2UiXSwiYWNpZCI6ImNvbS5raW5nc2NoYXQiLCJjaWQiOiI2N2VjZTkwMDAxNzNiYWI2NGFkMDNlNmUifQ.B40ZxKBNivMoiRGV_SKaRQFuSSgD5Yl6H_7yHSCd3dQH1GOBXEx_SB0F-G_7kSHCuSAV7oLC5qyg6oGPCRQtKvpkjZqm8hlxpQ-GOI46mwRa4oc1_KO6eUCbtGqFWRTE9QH_Bav1M7qjgRkzDAwxXHpZL3NY3rxyKDdML1-bcdvlnka88cbKek3leS-inI2SbI5i4YrSJueXZ-T9eJAZoqCRGJKqlT5n31wZGfb-6YWZz1csU79THDspFcr83WMObrxGFrptfwS8N2BdmNuq7fK1ltm0yAUbjy7UWgZsyRiKUPqshxql6UfKUi4X5hz4edLWykQwsucpg1-Qbx3yZw]
* [HTTP/2] [1] [content-type: application/json]
* [HTTP/2] [1] [content-length: 87]
> POST /api/v1/users/5d03686cde867f0001b9df3d/new_message HTTP/2
> Host: connect.kingsch.at
> User-Agent: curl/8.7.1
> Accept: */*
> Authorization: Bearer eyJhbGciOiJSUzI1NiIsImtpZCI6IjkzZjFkY2FjLTg5MjQtNDU3MS04ZWE5LWI3Mjc4MzY5Yzc5YiIsInR5cCI6IkpXVCJ9.eyJleHAiOjE3NDM1ODI5OTIxMDUsInN1YiI6IjY3YzZkNDg2MGIyMDk3NzAzNTg2NWY5OCIsImFsZyI6IlJTMjU2IiwiaXNzIjoia2luZ3NjaGF0IiwiYXVkIjpbInVzZXIiLCJwcm9maWxlIiwic2VuZF9jaGF0X21lc3NhZ2UiXSwiYWNpZCI6ImNvbS5raW5nc2NoYXQiLCJjaWQiOiI2N2VjZTkwMDAxNzNiYWI2NGFkMDNlNmUifQ.B40ZxKBNivMoiRGV_SKaRQFuSSgD5Yl6H_7yHSCd3dQH1GOBXEx_SB0F-G_7kSHCuSAV7oLC5qyg6oGPCRQtKvpkjZqm8hlxpQ-GOI46mwRa4oc1_KO6eUCbtGqFWRTE9QH_Bav1M7qjgRkzDAwxXHpZL3NY3rxyKDdML1-bcdvlnka88cbKek3leS-inI2SbI5i4YrSJueXZ-T9eJAZoqCRGJKqlT5n31wZGfb-6YWZz1csU79THDspFcr83WMObrxGFrptfwS8N2BdmNuq7fK1ltm0yAUbjy7UWgZsyRiKUPqshxql6UfKUi4X5hz4edLWykQwsucpg1-Qbx3yZw
> Content-Type: application/json
> Content-Length: 87
> 
* upload completely sent off: 87 bytes
< HTTP/2 404 
< date: Wed, 30 Apr 2025 07:15:23 GMT
< content-type: application/json; charset=utf-8
< content-length: 21
< access-control-allow-credentials: true
< access-control-allow-origin: *
< access-control-expose-headers: 
< cache-control: max-age=0, private, must-revalidate
< x-request-id: 4f9831f637aede23acbc89053ac108b4
< strict-transport-security: max-age=15724800; includeSubDomains
< 
* Connection #0 to host connect.kingsch.at left intact
{"error":"not found"}Mayowas-MacBook-Pro:kingschat maxwellvn$ :
Mayowas-MacBook-Pro:kingschat maxwellvn$ curl -v -X POST "https://connect.kingsch.at/api/users/5d03686cde867f0001b9df3d/new_message" \
> -H "Authorization: Bearer eyJhbGciOiJSUzI1NiIsImtpZCI6IjkzZjFkY2FjLTg5MjQtNDU3MS04ZWE5LWI3Mjc4MzY5Yzc5YiIsInR5cCI6IkpXVCJ9.eyJleHAiOjE3NDM1ODI5OTIxMDUsInN1YiI6IjY3YzZkNDg2MGIyMDk3NzAzNTg2NWY5OCIsImFsZyI6IlJTMjU2IiwiaXNzIjoia2luZ3NjaGF0IiwiYXVkIjpbInVzZXIiLCJwcm9maWxlIiwic2VuZF9jaGF0X21lc3NhZ2UiXSwiYWNpZCI6ImNvbS5raW5nc2NoYXQiLCJjaWQiOiI2N2VjZTkwMDAxNzNiYWI2NGFkMDNlNmUifQ.B40ZxKBNivMoiRGV_SKaRQFuSSgD5Yl6H_7yHSCd3dQH1GOBXEx_SB0F-G_7kSHCuSAV7oLC5qyg6oGPCRQtKvpkjZqm8hlxpQ-GOI46mwRa4oc1_KO6eUCbtGqFWRTE9QH_Bav1M7qjgRkzDAwxXHpZL3NY3rxyKDdML1-bcdvlnka88cbKek3leS-inI2SbI5i4YrSJueXZ-T9eJAZoqCRGJKqlT5n31wZGfb-6YWZz1csU79THDspFcr83WMObrxGFrptfwS8N2BdmNuq7fK1ltm0yAUbjy7UWgZsyRiKUPqshxql6UfKUi4X5hz4edLWykQwsucpg1-Qbx3yZw" \
> -H "Content-Type: application/json" \
> -d '{"message": {"body": {"text": {"body": "Hello, this is a test message from the API"}}}}'
Note: Unnecessary use of -X or --request, POST is already inferred.
* Host connect.kingsch.at:443 was resolved.
* IPv6: (none)
* IPv4: 54.195.114.156, 54.75.179.82, 54.75.169.230
*   Trying 54.195.114.156:443...
* Connected to connect.kingsch.at (54.195.114.156) port 443
* ALPN: curl offers h2,http/1.1
* (304) (OUT), TLS handshake, Client hello (1):
*  CAfile: /etc/ssl/cert.pem
*  CApath: none
* (304) (IN), TLS handshake, Server hello (2):
* (304) (IN), TLS handshake, Unknown (8):
* (304) (IN), TLS handshake, Certificate (11):
* (304) (IN), TLS handshake, CERT verify (15):
* (304) (IN), TLS handshake, Finished (20):
* (304) (OUT), TLS handshake, Finished (20):
* SSL connection using TLSv1.3 / AEAD-AES256-GCM-SHA384 / [blank] / UNDEF
* ALPN: server accepted h2
* Server certificate:
*  subject: CN=kingsch.at
*  start date: Apr  3 02:08:36 2025 GMT
*  expire date: Jul  2 02:08:35 2025 GMT
*  subjectAltName: host "connect.kingsch.at" matched cert's "connect.kingsch.at"
*  issuer: C=US; O=Let's Encrypt; CN=R10
*  SSL certificate verify ok.
* using HTTP/2
* [HTTP/2] [1] OPENED stream for https://connect.kingsch.at/api/users/5d03686cde867f0001b9df3d/new_message
* [HTTP/2] [1] [:method: POST]
* [HTTP/2] [1] [:scheme: https]
* [HTTP/2] [1] [:authority: connect.kingsch.at]
* [HTTP/2] [1] [:path: /api/users/5d03686cde867f0001b9df3d/new_message]
* [HTTP/2] [1] [user-agent: curl/8.7.1]
* [HTTP/2] [1] [accept: */*]
* [HTTP/2] [1] [authorization: Bearer eyJhbGciOiJSUzI1NiIsImtpZCI6IjkzZjFkY2FjLTg5MjQtNDU3MS04ZWE5LWI3Mjc4MzY5Yzc5YiIsInR5cCI6IkpXVCJ9.eyJleHAiOjE3NDM1ODI5OTIxMDUsInN1YiI6IjY3YzZkNDg2MGIyMDk3NzAzNTg2NWY5OCIsImFsZyI6IlJTMjU2IiwiaXNzIjoia2luZ3NjaGF0IiwiYXVkIjpbInVzZXIiLCJwcm9maWxlIiwic2VuZF9jaGF0X21lc3NhZ2UiXSwiYWNpZCI6ImNvbS5raW5nc2NoYXQiLCJjaWQiOiI2N2VjZTkwMDAxNzNiYWI2NGFkMDNlNmUifQ.B40ZxKBNivMoiRGV_SKaRQFuSSgD5Yl6H_7yHSCd3dQH1GOBXEx_SB0F-G_7kSHCuSAV7oLC5qyg6oGPCRQtKvpkjZqm8hlxpQ-GOI46mwRa4oc1_KO6eUCbtGqFWRTE9QH_Bav1M7qjgRkzDAwxXHpZL3NY3rxyKDdML1-bcdvlnka88cbKek3leS-inI2SbI5i4YrSJueXZ-T9eJAZoqCRGJKqlT5n31wZGfb-6YWZz1csU79THDspFcr83WMObrxGFrptfwS8N2BdmNuq7fK1ltm0yAUbjy7UWgZsyRiKUPqshxql6UfKUi4X5hz4edLWykQwsucpg1-Qbx3yZw]
* [HTTP/2] [1] [content-type: application/json]
* [HTTP/2] [1] [content-length: 87]
> POST /api/users/5d03686cde867f0001b9df3d/new_message HTTP/2
> Host: connect.kingsch.at
> User-Agent: curl/8.7.1
> Accept: */*
> Authorization: Bearer eyJhbGciOiJSUzI1NiIsImtpZCI6IjkzZjFkY2FjLTg5MjQtNDU3MS04ZWE5LWI3Mjc4MzY5Yzc5YiIsInR5cCI6IkpXVCJ9.eyJleHAiOjE3NDM1ODI5OTIxMDUsInN1YiI6IjY3YzZkNDg2MGIyMDk3NzAzNTg2NWY5OCIsImFsZyI6IlJTMjU2IiwiaXNzIjoia2luZ3NjaGF0IiwiYXVkIjpbInVzZXIiLCJwcm9maWxlIiwic2VuZF9jaGF0X21lc3NhZ2UiXSwiYWNpZCI6ImNvbS5raW5nc2NoYXQiLCJjaWQiOiI2N2VjZTkwMDAxNzNiYWI2NGFkMDNlNmUifQ.B40ZxKBNivMoiRGV_SKaRQFuSSgD5Yl6H_7yHSCd3dQH1GOBXEx_SB0F-G_7kSHCuSAV7oLC5qyg6oGPCRQtKvpkjZqm8hlxpQ-GOI46mwRa4oc1_KO6eUCbtGqFWRTE9QH_Bav1M7qjgRkzDAwxXHpZL3NY3rxyKDdML1-bcdvlnka88cbKek3leS-inI2SbI5i4YrSJueXZ-T9eJAZoqCRGJKqlT5n31wZGfb-6YWZz1csU79THDspFcr83WMObrxGFrptfwS8N2BdmNuq7fK1ltm0yAUbjy7UWgZsyRiKUPqshxql6UfKUi4X5hz4edLWykQwsucpg1-Qbx3yZw
> Content-Type: application/json
> Content-Length: 87
> 
* upload completely sent off: 87 bytes
< HTTP/2 401 
< date: Wed, 30 Apr 2025 07:15:42 GMT
< content-length: 0
< access-control-allow-credentials: true
< access-control-allow-origin: *
< access-control-expose-headers: 
< cache-control: max-age=0, private, must-revalidate
< x-request-id: b418ebaec64be5498a7940226e3e84f4
< strict-transport-security: max-age=15724800; includeSubDomains
< 
* Connection #0 to host connect.kingsch.at left intact
Mayowas-MacBook-Pro:kingschat maxwellvn$ :
Mayowas-MacBook-Pro:kingschat maxwellvn$ curl -v -X POST "https://connect.kingsch.at/api/users/5d03686cde867f0001b9df3d/new_message" \
> -H "Authorization: Bearer eyJhbGciOiJSUzI1NiIsImtpZCI6IjkzZjFkY2FjLTg5MjQtNDU3MS04ZWE5LWI3Mjc4MzY5Yzc5YiIsInR5cCI6IkpXVCJ9.eyJleHAiOjE3NDM1ODI5OTIxMDUsInN1YiI6IjY3YzZkNDg2MGIyMDk3NzAzNTg2NWY5OCIsImFsZyI6IlJTMjU2IiwiaXNzIjoia2luZ3NjaGF0IiwiYXVkIjpbInVzZXIiLCJwcm9maWxlIiwic2VuZF9jaGF0X21lc3NhZ2UiXSwiYWNpZCI6ImNvbS5raW5nc2NoYXQiLCJjaWQiOiI2N2VjZTkwMDAxNzNiYWI2NGFkMDNlNmUifQ.B40ZxKBNivMoiRGV_SKaRQFuSSgD5Yl6H_7yHSCd3dQH1GOBXEx_SB0F-G_7kSHCuSAV7oLC5qyg6oGPCRQtKvpkjZqm8hlxpQ-GOI46mwRa4oc1_KO6eUCbtGqFWRTE9QH_Bav1M7qjgRkzDAwxXHpZL3NY3rxyKDdML1-bcdvlnka88cbKek3leS-inI2SbI5i4YrSJueXZ-T9eJAZoqCRGJKqlT5n31wZGfb-6YWZz1csU79THDspFcr83WMObrxGFrptfwS8N2BdmNuq7fK1ltm0yAUbjy7UWgZsyRiKUPqshxql6UfKUi4X5hz4edLWykQwsucpg1-Qbx3yZw" \
> -H "Content-Type: application/json" \
> -d '{"body": "Hello, this is a test message from the API"}'
Note: Unnecessary use of -X or --request, POST is already inferred.
* Host connect.kingsch.at:443 was resolved.
* IPv6: (none)
* IPv4: 54.75.179.82, 54.195.114.156, 54.75.169.230
*   Trying 54.75.179.82:443...
* Connected to connect.kingsch.at (54.75.179.82) port 443
* ALPN: curl offers h2,http/1.1
* (304) (OUT), TLS handshake, Client hello (1):
*  CAfile: /etc/ssl/cert.pem
*  CApath: none
* (304) (IN), TLS handshake, Server hello (2):
* (304) (IN), TLS handshake, Unknown (8):
* (304) (IN), TLS handshake, Certificate (11):
* (304) (IN), TLS handshake, CERT verify (15):
* (304) (IN), TLS handshake, Finished (20):
* (304) (OUT), TLS handshake, Finished (20):
* SSL connection using TLSv1.3 / AEAD-AES256-GCM-SHA384 / [blank] / UNDEF
* ALPN: server accepted h2
* Server certificate:
*  subject: CN=kingsch.at
*  start date: Apr  3 02:08:36 2025 GMT
*  expire date: Jul  2 02:08:35 2025 GMT
*  subjectAltName: host "connect.kingsch.at" matched cert's "connect.kingsch.at"
*  issuer: C=US; O=Let's Encrypt; CN=R10
*  SSL certificate verify ok.
* using HTTP/2
* [HTTP/2] [1] OPENED stream for https://connect.kingsch.at/api/users/5d03686cde867f0001b9df3d/new_message
* [HTTP/2] [1] [:method: POST]
* [HTTP/2] [1] [:scheme: https]
* [HTTP/2] [1] [:authority: connect.kingsch.at]
* [HTTP/2] [1] [:path: /api/users/5d03686cde867f0001b9df3d/new_message]
* [HTTP/2] [1] [user-agent: curl/8.7.1]
* [HTTP/2] [1] [accept: */*]
* [HTTP/2] [1] [authorization: Bearer eyJhbGciOiJSUzI1NiIsImtpZCI6IjkzZjFkY2FjLTg5MjQtNDU3MS04ZWE5LWI3Mjc4MzY5Yzc5YiIsInR5cCI6IkpXVCJ9.eyJleHAiOjE3NDM1ODI5OTIxMDUsInN1YiI6IjY3YzZkNDg2MGIyMDk3NzAzNTg2NWY5OCIsImFsZyI6IlJTMjU2IiwiaXNzIjoia2luZ3NjaGF0IiwiYXVkIjpbInVzZXIiLCJwcm9maWxlIiwic2VuZF9jaGF0X21lc3NhZ2UiXSwiYWNpZCI6ImNvbS5raW5nc2NoYXQiLCJjaWQiOiI2N2VjZTkwMDAxNzNiYWI2NGFkMDNlNmUifQ.B40ZxKBNivMoiRGV_SKaRQFuSSgD5Yl6H_7yHSCd3dQH1GOBXEx_SB0F-G_7kSHCuSAV7oLC5qyg6oGPCRQtKvpkjZqm8hlxpQ-GOI46mwRa4oc1_KO6eUCbtGqFWRTE9QH_Bav1M7qjgRkzDAwxXHpZL3NY3rxyKDdML1-bcdvlnka88cbKek3leS-inI2SbI5i4YrSJueXZ-T9eJAZoqCRGJKqlT5n31wZGfb-6YWZz1csU79THDspFcr83WMObrxGFrptfwS8N2BdmNuq7fK1ltm0yAUbjy7UWgZsyRiKUPqshxql6UfKUi4X5hz4edLWykQwsucpg1-Qbx3yZw]
* [HTTP/2] [1] [content-type: application/json]
* [HTTP/2] [1] [content-length: 54]
> POST /api/users/5d03686cde867f0001b9df3d/new_message HTTP/2
> Host: connect.kingsch.at
> User-Agent: curl/8.7.1
> Accept: */*
> Authorization: Bearer eyJhbGciOiJSUzI1NiIsImtpZCI6IjkzZjFkY2FjLTg5MjQtNDU3MS04ZWE5LWI3Mjc4MzY5Yzc5YiIsInR5cCI6IkpXVCJ9.eyJleHAiOjE3NDM1ODI5OTIxMDUsInN1YiI6IjY3YzZkNDg2MGIyMDk3NzAzNTg2NWY5OCIsImFsZyI6IlJTMjU2IiwiaXNzIjoia2luZ3NjaGF0IiwiYXVkIjpbInVzZXIiLCJwcm9maWxlIiwic2VuZF9jaGF0X21lc3NhZ2UiXSwiYWNpZCI6ImNvbS5raW5nc2NoYXQiLCJjaWQiOiI2N2VjZTkwMDAxNzNiYWI2NGFkMDNlNmUifQ.B40ZxKBNivMoiRGV_SKaRQFuSSgD5Yl6H_7yHSCd3dQH1GOBXEx_SB0F-G_7kSHCuSAV7oLC5qyg6oGPCRQtKvpkjZqm8hlxpQ-GOI46mwRa4oc1_KO6eUCbtGqFWRTE9QH_Bav1M7qjgRkzDAwxXHpZL3NY3rxyKDdML1-bcdvlnka88cbKek3leS-inI2SbI5i4YrSJueXZ-T9eJAZoqCRGJKqlT5n31wZGfb-6YWZz1csU79THDspFcr83WMObrxGFrptfwS8N2BdmNuq7fK1ltm0yAUbjy7UWgZsyRiKUPqshxql6UfKUi4X5hz4edLWykQwsucpg1-Qbx3yZw
> Content-Type: application/json
> Content-Length: 54
> 
* upload completely sent off: 54 bytes
< HTTP/2 401 
< date: Wed, 30 Apr 2025 07:15:59 GMT
< content-length: 0
< access-control-allow-credentials: true
< access-control-allow-origin: *
< access-control-expose-headers: 
< cache-control: max-age=0, private, must-revalidate
< x-request-id: 516a92241be98ceb3a571d9c3fd592af
< strict-transport-security: max-age=15724800; includeSubDomains
< 
* Connection #0 to host connect.kingsch.at left intact
Mayowas-MacBook-Pro:kingschat maxwellvn$ :
Mayowas-MacBook-Pro:kingschat maxwellvn$ curl -v -X POST "https://connect.kingsch.at/api/users/5d03686cde867f0001b9df3d/new_message" \
> -H "Authorization: Bearer eyJhbGciOiJSUzI1NiIsImtpZCI6IjkzZjFkY2FjLTg5MjQtNDU3MS04ZWE5LWI3Mjc4MzY5Yzc5YiIsInR5cCI6IkpXVCJ9.eyJleHAiOjE3NDYwMDA5OTk4NjksInN1YiI6IjY3YzZkNDg2MGIyMDk3NzAzNTg2NWY5OCIsImFsZyI6IlJTMjU2IiwiaXNzIjoia2luZ3NjaGF0IiwiYXVkIjpbInVzZXIiLCJwcm9maWxlIiwic2VuZF9jaGF0X21lc3NhZ2UiXSwiYWNpZCI6ImNvbS5raW5nc2NoYXQiLCJjaWQiOiI2ODExY2U1NzBkMmEyNmMwYjVlOGMxMzUifQ.VkXPeibT_wv6MvF8AGDTJqpdohJ2pTIx0kf7ZR6vKg1S3FtkEQfBbtJOHxuYmzNsqc3i61gOP9MBjHcbeg3TmLqoPEiV5UE8H6T_pwemj9Zl1cr5_-UR-YI2g59RxNeMHHbFUd1O5wIjZsmS6MIUSICEAps-ZR7o2SRkdCOzyOLpwsdb4FV8HHeWqwik9hI8bAQzovvXnveaKT308FioR_lYaqUHujvwww2mrKOkefkeLeFTSQeyiestrB0yHv1_9jtoShNf4ZG8cnZW4B_3Iofz35HN575UFDkydDSR-_iGyzwro_l4NKe7aR3kgHTboLTtjvDFj__rP9B4Qzb7gQ" \
> -H "Content-Type: application/json" \
> -d '{"message": {"body": {"text": {"body": "Hello, this is a test message from the API"}}}}'
Note: Unnecessary use of -X or --request, POST is already inferred.
* Host connect.kingsch.at:443 was resolved.
* IPv6: (none)
* IPv4: 54.75.179.82, 54.195.114.156, 54.75.169.230
*   Trying 54.75.179.82:443...
* Connected to connect.kingsch.at (54.75.179.82) port 443
* ALPN: curl offers h2,http/1.1
* (304) (OUT), TLS handshake, Client hello (1):
*  CAfile: /etc/ssl/cert.pem
*  CApath: none
* (304) (IN), TLS handshake, Server hello (2):
* (304) (IN), TLS handshake, Unknown (8):
* (304) (IN), TLS handshake, Certificate (11):
* (304) (IN), TLS handshake, CERT verify (15):
* (304) (IN), TLS handshake, Finished (20):
* (304) (OUT), TLS handshake, Finished (20):
* SSL connection using TLSv1.3 / AEAD-AES256-GCM-SHA384 / [blank] / UNDEF
* ALPN: server accepted h2
* Server certificate:
*  subject: CN=kingsch.at
*  start date: Apr  3 02:08:36 2025 GMT
*  expire date: Jul  2 02:08:35 2025 GMT
*  subjectAltName: host "connect.kingsch.at" matched cert's "connect.kingsch.at"
*  issuer: C=US; O=Let's Encrypt; CN=R10
*  SSL certificate verify ok.
* using HTTP/2
* [HTTP/2] [1] OPENED stream for https://connect.kingsch.at/api/users/5d03686cde867f0001b9df3d/new_message
* [HTTP/2] [1] [:method: POST]
* [HTTP/2] [1] [:scheme: https]
* [HTTP/2] [1] [:authority: connect.kingsch.at]
* [HTTP/2] [1] [:path: /api/users/5d03686cde867f0001b9df3d/new_message]
* [HTTP/2] [1] [user-agent: curl/8.7.1]
* [HTTP/2] [1] [accept: */*]
* [HTTP/2] [1] [authorization: Bearer eyJhbGciOiJSUzI1NiIsImtpZCI6IjkzZjFkY2FjLTg5MjQtNDU3MS04ZWE5LWI3Mjc4MzY5Yzc5YiIsInR5cCI6IkpXVCJ9.eyJleHAiOjE3NDYwMDA5OTk4NjksInN1YiI6IjY3YzZkNDg2MGIyMDk3NzAzNTg2NWY5OCIsImFsZyI6IlJTMjU2IiwiaXNzIjoia2luZ3NjaGF0IiwiYXVkIjpbInVzZXIiLCJwcm9maWxlIiwic2VuZF9jaGF0X21lc3NhZ2UiXSwiYWNpZCI6ImNvbS5raW5nc2NoYXQiLCJjaWQiOiI2ODExY2U1NzBkMmEyNmMwYjVlOGMxMzUifQ.VkXPeibT_wv6MvF8AGDTJqpdohJ2pTIx0kf7ZR6vKg1S3FtkEQfBbtJOHxuYmzNsqc3i61gOP9MBjHcbeg3TmLqoPEiV5UE8H6T_pwemj9Zl1cr5_-UR-YI2g59RxNeMHHbFUd1O5wIjZsmS6MIUSICEAps-ZR7o2SRkdCOzyOLpwsdb4FV8HHeWqwik9hI8bAQzovvXnveaKT308FioR_lYaqUHujvwww2mrKOkefkeLeFTSQeyiestrB0yHv1_9jtoShNf4ZG8cnZW4B_3Iofz35HN575UFDkydDSR-_iGyzwro_l4NKe7aR3kgHTboLTtjvDFj__rP9B4Qzb7gQ]
* [HTTP/2] [1] [content-type: application/json]
* [HTTP/2] [1] [content-length: 87]
> POST /api/users/5d03686cde867f0001b9df3d/new_message HTTP/2
> Host: connect.kingsch.at
> User-Agent: curl/8.7.1
> Accept: */*
> Authorization: Bearer eyJhbGciOiJSUzI1NiIsImtpZCI6IjkzZjFkY2FjLTg5MjQtNDU3MS04ZWE5LWI3Mjc4MzY5Yzc5YiIsInR5cCI6IkpXVCJ9.eyJleHAiOjE3NDYwMDA5OTk4NjksInN1YiI6IjY3YzZkNDg2MGIyMDk3NzAzNTg2NWY5OCIsImFsZyI6IlJTMjU2IiwiaXNzIjoia2luZ3NjaGF0IiwiYXVkIjpbInVzZXIiLCJwcm9maWxlIiwic2VuZF9jaGF0X21lc3NhZ2UiXSwiYWNpZCI6ImNvbS5raW5nc2NoYXQiLCJjaWQiOiI2ODExY2U1NzBkMmEyNmMwYjVlOGMxMzUifQ.VkXPeibT_wv6MvF8AGDTJqpdohJ2pTIx0kf7ZR6vKg1S3FtkEQfBbtJOHxuYmzNsqc3i61gOP9MBjHcbeg3TmLqoPEiV5UE8H6T_pwemj9Zl1cr5_-UR-YI2g59RxNeMHHbFUd1O5wIjZsmS6MIUSICEAps-ZR7o2SRkdCOzyOLpwsdb4FV8HHeWqwik9hI8bAQzovvXnveaKT308FioR_lYaqUHujvwww2mrKOkefkeLeFTSQeyiestrB0yHv1_9jtoShNf4ZG8cnZW4B_3Iofz35HN575UFDkydDSR-_iGyzwro_l4NKe7aR3kgHTboLTtjvDFj__rP9B4Qzb7gQ
> Content-Type: application/json
> Content-Length: 87
> 
* upload completely sent off: 87 bytes
< HTTP/2 200 
< date: Wed, 30 Apr 2025 07:18:02 GMT
< content-type: application/json; charset=utf-8
< content-length: 2
< access-control-allow-credentials: true
< access-control-allow-origin: *
< access-control-expose-headers: 
< cache-control: max-age=0, private, must-revalidate
< x-request-id: 6b46e3e0d5fa06e74b465ef3681c145f
< strict-transport-security: max-age=15724800; includeSubDomains
< 
* Connection #0 to host connect.kingsch.at left intact
""Mayowas-MacBook-Pro:kingschat maxwellvn$ :
Mayowas-MacBook-Pro:kingschat maxwellvn$ curl -v -X POST "https://connect.kingsch.at/api/users/5d03686cde867f0001b9df3d/new_message" \
> -H "Authorization: Bearer eyJhbGciOiJSUzI1NiIsImtpZCI6IjkzZjFkY2FjLTg5MjQtNDU3MS04ZWE5LWI3Mjc4MzY5Yzc5YiIsInR5cCI6IkpXVCJ9.eyJleHAiOjE3NDYwMDA5OTk4NjksInN1YiI6IjY3YzZkNDg2MGIyMDk3NzAzNTg2NWY5OCIsImFsZyI6IlJTMjU2IiwiaXNzIjoia2luZ3NjaGF0IiwiYXVkIjpbInVzZXIiLCJwcm9maWxlIiwic2VuZF9jaGF0X21lc3NhZ2UiXSwiYWNpZCI6ImNvbS5raW5nc2NoYXQiLCJjaWQiOiI2ODExY2U1NzBkMmEyNmMwYjVlOGMxMzUifQ.VkXPeibT_wv6MvF8AGDTJqpdohJ2pTIx0kf7ZR6vKg1S3FtkEQfBbtJOHxuYmzNsqc3i61gOP9MBjHcbeg3TmLqoPEiV5UE8H6T_pwemj9Zl1cr5_-UR-YI2g59RxNeMHHbFUd1O5wIjZsmS6MIUSICEAps-ZR7o2SRkdCOzyOLpwsdb4FV8HHeWqwik9hI8bAQzovvXnveaKT308FioR_lYaqUHujvwww2mrKOkefkeLeFTSQeyiestrB0yHv1_9jtoShNf4ZG8cnZW4B_3Iofz35HN575UFDkydDSR-_iGyzwro_l4NKe7aR3kgHTboLTtjvDFj__rP9B4Qzb7gQ" \
> -H "Content-Type: application/json" \
> -d '{"message": {"body": {"text": {"body": "This is a second test message sent via API"}}}}'
Note: Unnecessary use of -X or --request, POST is already inferred.
* Host connect.kingsch.at:443 was resolved.
* IPv6: (none)
* IPv4: 54.75.179.82, 54.75.169.230, 54.195.114.156
*   Trying 54.75.179.82:443...
* Connected to connect.kingsch.at (54.75.179.82) port 443
* ALPN: curl offers h2,http/1.1
* (304) (OUT), TLS handshake, Client hello (1):
*  CAfile: /etc/ssl/cert.pem
*  CApath: none
* (304) (IN), TLS handshake, Server hello (2):
* (304) (IN), TLS handshake, Unknown (8):
* (304) (IN), TLS handshake, Certificate (11):
* (304) (IN), TLS handshake, CERT verify (15):
* (304) (IN), TLS handshake, Finished (20):
* (304) (OUT), TLS handshake, Finished (20):
* SSL connection using TLSv1.3 / AEAD-AES256-GCM-SHA384 / [blank] / UNDEF
* ALPN: server accepted h2
* Server certificate:
*  subject: CN=kingsch.at
*  start date: Apr  3 02:08:36 2025 GMT
*  expire date: Jul  2 02:08:35 2025 GMT
*  subjectAltName: host "connect.kingsch.at" matched cert's "connect.kingsch.at"
*  issuer: C=US; O=Let's Encrypt; CN=R10
*  SSL certificate verify ok.
* using HTTP/2
* [HTTP/2] [1] OPENED stream for https://connect.kingsch.at/api/users/5d03686cde867f0001b9df3d/new_message
* [HTTP/2] [1] [:method: POST]
* [HTTP/2] [1] [:scheme: https]
* [HTTP/2] [1] [:authority: connect.kingsch.at]
* [HTTP/2] [1] [:path: /api/users/5d03686cde867f0001b9df3d/new_message]
* [HTTP/2] [1] [user-agent: curl/8.7.1]
* [HTTP/2] [1] [accept: */*]
* [HTTP/2] [1] [authorization: Bearer eyJhbGciOiJSUzI1NiIsImtpZCI6IjkzZjFkY2FjLTg5MjQtNDU3MS04ZWE5LWI3Mjc4MzY5Yzc5YiIsInR5cCI6IkpXVCJ9.eyJleHAiOjE3NDYwMDA5OTk4NjksInN1YiI6IjY3YzZkNDg2MGIyMDk3NzAzNTg2NWY5OCIsImFsZyI6IlJTMjU2IiwiaXNzIjoia2luZ3NjaGF0IiwiYXVkIjpbInVzZXIiLCJwcm9maWxlIiwic2VuZF9jaGF0X21lc3NhZ2UiXSwiYWNpZCI6ImNvbS5raW5nc2NoYXQiLCJjaWQiOiI2ODExY2U1NzBkMmEyNmMwYjVlOGMxMzUifQ.VkXPeibT_wv6MvF8AGDTJqpdohJ2pTIx0kf7ZR6vKg1S3FtkEQfBbtJOHxuYmzNsqc3i61gOP9MBjHcbeg3TmLqoPEiV5UE8H6T_pwemj9Zl1cr5_-UR-YI2g59RxNeMHHbFUd1O5wIjZsmS6MIUSICEAps-ZR7o2SRkdCOzyOLpwsdb4FV8HHeWqwik9hI8bAQzovvXnveaKT308FioR_lYaqUHujvwww2mrKOkefkeLeFTSQeyiestrB0yHv1_9jtoShNf4ZG8cnZW4B_3Iofz35HN575UFDkydDSR-_iGyzwro_l4NKe7aR3kgHTboLTtjvDFj__rP9B4Qzb7gQ]
* [HTTP/2] [1] [content-type: application/json]
* [HTTP/2] [1] [content-length: 87]
> POST /api/users/5d03686cde867f0001b9df3d/new_message HTTP/2
> Host: connect.kingsch.at
> User-Agent: curl/8.7.1
> Accept: */*
> Authorization: Bearer eyJhbGciOiJSUzI1NiIsImtpZCI6IjkzZjFkY2FjLTg5MjQtNDU3MS04ZWE5LWI3Mjc4MzY5Yzc5YiIsInR5cCI6IkpXVCJ9.eyJleHAiOjE3NDYwMDA5OTk4NjksInN1YiI6IjY3YzZkNDg2MGIyMDk3NzAzNTg2NWY5OCIsImFsZyI6IlJTMjU2IiwiaXNzIjoia2luZ3NjaGF0IiwiYXVkIjpbInVzZXIiLCJwcm9maWxlIiwic2VuZF9jaGF0X21lc3NhZ2UiXSwiYWNpZCI6ImNvbS5raW5nc2NoYXQiLCJjaWQiOiI2ODExY2U1NzBkMmEyNmMwYjVlOGMxMzUifQ.VkXPeibT_wv6MvF8AGDTJqpdohJ2pTIx0kf7ZR6vKg1S3FtkEQfBbtJOHxuYmzNsqc3i61gOP9MBjHcbeg3TmLqoPEiV5UE8H6T_pwemj9Zl1cr5_-UR-YI2g59RxNeMHHbFUd1O5wIjZsmS6MIUSICEAps-ZR7o2SRkdCOzyOLpwsdb4FV8HHeWqwik9hI8bAQzovvXnveaKT308FioR_lYaqUHujvwww2mrKOkefkeLeFTSQeyiestrB0yHv1_9jtoShNf4ZG8cnZW4B_3Iofz35HN575UFDkydDSR-_iGyzwro_l4NKe7aR3kgHTboLTtjvDFj__rP9B4Qzb7gQ
> Content-Type: application/json
> Content-Length: 87
> 
* upload completely sent off: 87 bytes
< HTTP/2 200 
< date: Wed, 30 Apr 2025 07:18:20 GMT
< content-type: application/json; charset=utf-8
< content-length: 2
< access-control-allow-credentials: true
< access-control-allow-origin: *
< access-control-expose-headers: 
< cache-control: max-age=0, private, must-revalidate
< x-request-id: 6f8b568cf579ee7b36e27b8ad81b241f
< strict-transport-security: max-age=15724800; includeSubDomains
< 
* Connection #0 to host connect.kingsch.at left intact
""Mayowas-MacBook-Pro:kingschat maxwellvn$ curl -v -X POST "https://connect.kinch.at/api/users/5d03686cde867f0001b9df3d/new_message" -H "Authorization: Bearer eyJhbGciOiJSUzI1NiIsImtpZCI6IjkzZjFkY2FjLTg5MjQtNDU3MS04ZWE5LWI3Mjc4MzY5Yzc5YiIsInR5cCI6IkpXVCJ9.eyJleHAiOjE3NDYwMDA5OTk4NjksInN1YiI6IjY3YzZkNDg2MGIyMDk3NzAzNTg2NWY5OCIsImFsZyI6IlJTMjU2IiwiaXNzIjoia2luZ3NjaGF0IiwiYXVkIjpbInVzZXIiLCJwcm9maWxlIiwic2VuZF9jaGF0X21lc3NhZ2UiXSwiYWNpZCI6ImNvbS5raW5nc2NoYXQiLCJjaWQiOiI2ODExY2U1NzBkMmEyNmMwYjVlOGMxMzUifQ.VkXPeibT_wv6MvF8AGDTJqpdohJ2pTIx0kf7ZR6vKg1S3FtkEQfBbtJOHxuYmzNsqc3i61gOP9MBjHcbeg3TmLqoPEiV5UE8H6T_pwemj9Zl1cr5_-UR-YI2g59RxNeMHHbFUd1O5wIjZsmS6MIUSICEAps-ZR7o2SRkdCOzyOLpwsdb4FV8HHeWqwik9hI8bAQzovvXnveaKT308FioR_lYaqUHujvwww2mrKOkefkeLeFTSQeyiestrB0yHv1_9jtoShNf4ZG8cnZW4B_3Iofz35HN575UFDkydDSR-_iGyzwro_l4NKe7aR3kgHTboLTtjvDFj__rP9B4Qzb7gQ" -H "Content-Type: application/json" -d '{"message": {"body": {"text": {"body": "This is a second test message sent via API"}}}}'
Note: Unnecessary use of -X or --request, POST is already inferred.
* Host connect.kingsch.at:443 was resolved.
* IPv6: (none)
* IPv4: 54.75.179.82, 54.195.114.156, 54.75.169.230
*   Trying 54.75.179.82:443...
* Connected to connect.kingsch.at (54.75.179.82) port 443
* ALPN: curl offers h2,http/1.1
* (304) (OUT), TLS handshake, Client hello (1):
*  CAfile: /etc/ssl/cert.pem
*  CApath: none
* (304) (IN), TLS handshake, Server hello (2):
* (304) (IN), TLS handshake, Unknown (8):
* (304) (IN), TLS handshake, Certificate (11):
* (304) (IN), TLS handshake, CERT verify (15):
* (304) (IN), TLS handshake, Finished (20):
* (304) (OUT), TLS handshake, Finished (20):
* SSL connection using TLSv1.3 / AEAD-AES256-GCM-SHA384 / [blank] / UNDEF
* ALPN: server accepted h2
* Server certificate:
*  subject: CN=kingsch.at
*  start date: Apr  3 02:08:36 2025 GMT
*  expire date: Jul  2 02:08:35 2025 GMT
*  subjectAltName: host "connect.kingsch.at" matched cert's "connect.kingsch.at"
*  issuer: C=US; O=Let's Encrypt; CN=R10
*  SSL certificate verify ok.
* using HTTP/2
* [HTTP/2] [1] OPENED stream for https://connect.kingsch.at/api/users/5d03686cde867f0001b9df3d/new_message
* [HTTP/2] [1] [:method: POST]
* [HTTP/2] [1] [:scheme: https]
* [HTTP/2] [1] [:authority: connect.kingsch.at]
* [HTTP/2] [1] [:path: /api/users/5d03686cde867f0001b9df3d/new_message]
* [HTTP/2] [1] [user-agent: curl/8.7.1]
* [HTTP/2] [1] [accept: */*]
* [HTTP/2] [1] [authorization: Bearer eyJhbGciOiJSUzI1NiIsImtpZCI6IjkzZjFkY2FjLTg5MjQtNDU3MS04ZWE5LWI3Mjc4MzY5Yzc5YiIsInR5cCI6IkpXVCJ9.eyJleHAiOjE3NDYwMDA5OTk4NjksInN1YiI6IjY3YzZkNDg2MGIyMDk3NzAzNTg2NWY5OCIsImFsZyI6IlJTMjU2IiwiaXNzIjoia2luZ3NjaGF0IiwiYXVkIjpbInVzZXIiLCJwcm9maWxlIiwic2VuZF9jaGF0X21lc3NhZ2UiXSwiYWNpZCI6ImNvbS5raW5nc2NoYXQiLCJjaWQiOiI2ODExY2U1NzBkMmEyNmMwYjVlOGMxMzUifQ.VkXPeibT_wv6MvF8AGDTJqpdohJ2pTIx0kf7ZR6vKg1S3FtkEQfBbtJOHxuYmzNsqc3i61gOP9MBjHcbeg3TmLqoPEiV5UE8H6T_pwemj9Zl1cr5_-UR-YI2g59RxNeMHHbFUd1O5wIjZsmS6MIUSICEAps-ZR7o2SRkdCOzyOLpwsdb4FV8HHeWqwik9hI8bAQzovvXnveaKT308FioR_lYaqUHujvwww2mrKOkefkeLeFTSQeyiestrB0yHv1_9jtoShNf4ZG8cnZW4B_3Iofz35HN575UFDkydDSR-_iGyzwro_l4NKe7aR3kgHTboLTtjvDFj__rP9B4Qzb7gQ]
* [HTTP/2] [1] [content-type: application/json]
* [HTTP/2] [1] [content-length: 87]
> POST /api/users/5d03686cde867f0001b9df3d/new_message HTTP/2
> Host: connect.kingsch.at
> User-Agent: curl/8.7.1
> Accept: */*
> Authorization: Bearer eyJhbGciOiJSUzI1NiIsImtpZCI6IjkzZjFkY2FjLTg5MjQtNDU3MS04ZWE5LWI3Mjc4MzY5Yzc5YiIsInR5cCI6IkpXVCJ9.eyJleHAiOjE3NDYwMDA5OTk4NjksInN1YiI6IjY3YzZkNDg2MGIyMDk3NzAzNTg2NWY5OCIsImFsZyI6IlJTMjU2IiwiaXNzIjoia2luZ3NjaGF0IiwiYXVkIjpbInVzZXIiLCJwcm9maWxlIiwic2VuZF9jaGF0X21lc3NhZ2UiXSwiYWNpZCI6ImNvbS5raW5nc2NoYXQiLCJjaWQiOiI2ODExY2U1NzBkMmEyNmMwYjVlOGMxMzUifQ.VkXPeibT_wv6MvF8AGDTJqpdohJ2pTIx0kf7ZR6vKg1S3FtkEQfBbtJOHxuYmzNsqc3i61gOP9MBjHcbeg3TmLqoPEiV5UE8H6T_pwemj9Zl1cr5_-UR-YI2g59RxNeMHHbFUd1O5wIjZsmS6MIUSICEAps-ZR7o2SRkdCOzyOLpwsdb4FV8HHeWqwik9hI8bAQzovvXnveaKT308FioR_lYaqUHujvwww2mrKOkefkeLeFTSQeyiestrB0yHv1_9jtoShNf4ZG8cnZW4B_3Iofz35HN575UFDkydDSR-_iGyzwro_l4NKe7aR3kgHTboLTtjvDFj__rP9B4Qzb7gQ
> Content-Type: application/json
> Content-Length: 87
> 
* upload completely sent off: 87 bytes
< HTTP/2 200 
< date: Wed, 30 Apr 2025 07:20:11 GMT
< content-type: application/json; charset=utf-8
< content-length: 2
< access-control-allow-credentials: true
< access-control-allow-origin: *
< access-control-expose-headers: 
< cache-control: max-age=0, private, must-revalidate
< x-request-id: 6168e78ca81efcf4649c7617e9e19483
< strict-transport-security: max-age=15724800; includeSubDomains
< 
* Connection #0 to host connect.kingsch.at left intact
Mayowas-MacBook-Pro:kingschat maxwellvn$ curl -v -X POST "https://connect.kingsch.at/api/users/67c6d4860b20977035865f98/new_message" -H "Authorization: Bearer eyJhbGciOiJSUzI1NiIsImtpZCI6IjkzZjFkY2FjLTg5MjQtNDU3MS04ZWE5LWI3Mjc4MzY5Yzc5YiIsInR5cCI6IkpXVCJ9.eyJleHAiOjE3NDYwMDA5OTk4NjksInN1YiI6IjY3YzZkNDg2MGIyMDk3NzAzNTg2NWY5OCIsImFsZyI6IlJTMjU2IiwiaXNzIjoia2luZ3NjaGF0IiwiYXVkIjpbInVzZXIiLCJwcm9maWxlIiwic2VuZF9jaGF0X21lc3NhZ2UiXSwiYWNpZCI6ImNvbS5raW5nc2NoYXQiLCJjaWQiOiI2ODExY2U1NzBkMmEyNmMwYjVlOGMxMzUifQ.VkXPeibT_wv6MvF8AGDTJqpdohJ2pTIx0kf7ZR6vKg1S3FtkEQfBbtJOHxuYmzNsqc3i61gOP9MBjHcbeg3TmLqoPEiV5UE8H6T_pwemj9Zl1cr5_-UR-YI2g59RxNeMHHbFUd1O5wIjZsmS6MIUSICEAps-ZR7o2SRkdCOzyOLpwsdb4FV8HHeWqwik9hI8bAQzovvXnveaKT308FioR_lYaqUHujvwww2mrKOkefkeLeFTSQeyiestrB0yHv1_9jtoShNf4ZG8cnZW4B_3Iofz35HN575UFDkydDSR-_iGyzwro_l4NKe7aR3kgHTboLTtjvDFj__rP9B4Qzb7gQ" -H "Content-Type: application/json" -d '{"message": {"body": {"text": {"body": "This is a second test message sent via API"}}}}'