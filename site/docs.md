# OI:LOOP MCP server

OI:LOOP runs a public [Model Context Protocol](https://modelcontextprotocol.io) server. AI assistants can use it to answer questions about OI:LOOP or to add a person to the early API waitlist.

- Endpoint: `https://oiloop.com/mcp`
- Transport: Streamable HTTP, stateless JSON-RPC 2.0 (POST). No server-sent events.
- Authentication: none. See [auth.md](https://oiloop.com/auth.md).
- OpenAPI description: https://oiloop.com/openapi.json
- API catalog (RFC 9727): https://oiloop.com/.well-known/api-catalog
- Health: https://oiloop.com/health

## Connect

- **Claude:** Settings → Connectors → Add custom connector → paste `https://oiloop.com/mcp`.
- **ChatGPT:** Settings → Apps & Connectors → developer mode → Create → paste the URL.
- **Other clients:**

```json
{ "mcpServers": { "oiloop": { "url": "https://oiloop.com/mcp" } } }
```

## Tools

| Tool | What it does | Writes data |
|---|---|---|
| `about_oiloop` | Company overview, business model, stage, contact | No |
| `explain_technology` | How the passive optical weight bank works | No |
| `get_energy_comparison` | Energy per multiply against three anonymised chips, with caveats | No |
| `get_facility_projection` | Plan for year 2 to 5: panel area, racks, tokens per second, effective compute | No |
| `join_waitlist` | Adds an email to the early API waitlist and notifies the team | Yes |

Call `join_waitlist` only when the person asks to join and gives their own email. Requests are limited to 5 per hour per address.

## Example

```http
POST /mcp HTTP/1.1
Host: oiloop.com
Content-Type: application/json
Accept: application/json, text/event-stream

{"jsonrpc":"2.0","id":1,"method":"tools/call","params":{"name":"get_facility_projection","arguments":{"year":3}}}
```

All performance figures are design targets or models, not measurements. Contact: info@oiloop.com
