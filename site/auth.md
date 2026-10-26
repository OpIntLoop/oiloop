# OI:LOOP auth.md

How AI agents authenticate with OI:LOOP services.

## Audience

AI agents and assistants acting for a person who wants information about OI:LOOP or wants to join the early API waitlist.

## Services

| Service | URL | Authentication |
|---|---|---|
| MCP server | `https://oiloop.com/mcp` | None. Public and anonymous. |
| Health check | `https://oiloop.com/health` | None |
| Token API (inference) | Not yet available | Issued to waitlist partners when the pilot opens |

## Registration and provisioning

No registration is needed to use the MCP server. There is no OAuth authorization server and no agent registration endpoint at this time.

Supported method:

- **Anonymous.** Send MCP requests to `https://oiloop.com/mcp` without credentials. No token, cookie or API key is used or accepted.

Access to the future token API is provisioned by request:

1. Call the MCP tool `join_waitlist` with the person's own work email (and optionally name, company and use case), or
2. Email info@oiloop.com with the subject "Early waitlist".

OI:LOOP replies from info@oiloop.com. API credentials, when issued, will be sent to the registered person, not to the agent, and this file will be updated with the credential type and how to send it.

## Credential use

None today. Do not send secrets, passwords or personal tokens to the MCP server.

## Limits

`join_waitlist` accepts at most 5 requests per hour from one address. Only call it when the person asked to join and supplied their own email.

Contact: info@oiloop.com · Last updated: 2026-10-26
