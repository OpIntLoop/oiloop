# OI:LOOP

> Weights in Light. Compute in Motion.

OI:LOOP (Optical Integration Loop) prints the weights of AI models into glass. Light crosses the glass once, and every multiplication in the layer happens in that pass. The panels run in OI:LOOP's own inference facility, and OI:LOOP sells the tokens through an API. It does not sell chips.

- Website: https://oiloop.com
- Early waitlist and contact: info@oiloop.com
- MCP server for AI assistants: https://oiloop.com/mcp
- Overview for AI agents: https://oiloop.com/llms.txt

## Key figures

| Item | Value |
|---|---|
| Carrier wavelength | 1550 nm (fibre-network wavelength) |
| Power in the weight bank | 0 W |
| Energy per multiply | 3.3–6.3 fJ (core-level design model, not yet measured) |
| First model | about 0.5 billion parameters, 24 layers, 896 channels |

## The energy wall

AI spends its energy moving weights, not multiplying them. Every token a language model writes re-reads all of its weights. The arithmetic is cheap; moving the weights is not.

- World data-centre electricity: 415 TWh in 2024, a projected 945 TWh in 2030, about 2.3× (IEA, Energy and AI, 2025, base case).
- Data-centre capacity demand: from about 60 GW to 171–219 GW by 2030, most of it for AI (McKinsey, 2024, midpoint scenario).
- Cloud AI spend in 2026: inference $23.3B against training $19B, 55% inference, rising to 59% in 2027 (Gartner, August 2026).
- For a 7B-class model, reading weights from memory costs 60–70× more energy than the multiplications (OI:LOOP internal calculation, HBM ~4 pJ/bit).

## Physical Weight Encoding

A memory-bound chip fetches each weight, multiplies, then fetches the next. OI:LOOP prints the weights into glass instead. The input arrives as light, and all the products form as it crosses.

## The Optical Integration Loop

One lap of the loop is one layer of the model.

1. **Encode (laser source).** The token's input vector is written onto 1550 nm laser light, one channel per value.
2. **Multiply (passive weight bank).** Each printed cell passes a fixed share of the light; that share is the weight, set when the panel was printed. No heaters and no clock in the bank.
3. **Sum (photodetector).** Every path in a row meets one detector. The current it reads is the sum of the products, one entry of W·x per detector.
4. **Return (electronics).** Converters hand the sums to digital logic, which applies the activation and attention. The result is written back onto light for the next layer.

Signed weights use two rails (W⁺ − W⁻). The optical pass stays one pass as the matrix grows; converters and electronics still grow with matrix size.

## PMP-1550, the Zero-Gate Passive Matrix Processor

A glass panel that carries a model's weights as printed optical paths.

- Weight cell: non-resonant passive attenuator. The weight is light intensity, so path-length and phase errors do not move it.
- Material: optical polymer on glass, printed with UV lithography; nanoimprint at volume.
- Power in the bank: 0 W. Power goes to the laser, modulators, detectors and converters.
- Optical scope: MLP layers, about 81% of multiplies per token.
- One panel set per model version. First target: a half-billion-parameter model.

## Energy per multiply

| Chip | Energy per multiply | OI:LOOP advantage |
|---|---|---|
| Chip A, current flagship GPU | ~200 fJ | 30–60× |
| Chip B, previous-generation GPU | ~1,400 fJ | 220–420× (indicative) |
| Chip C, photonic accelerator, measured | ~2,500 fJ | 400–760× (indicative) |

Chips A–C are commercial products, anonymised. OI:LOOP figures come from an internal core-level design model with a realistic ADC and have not been measured. Accuracy simulation on a half-billion-parameter model: 8-bit weights +0.04 perplexity; 1% optical error +0.13.

## Optical Inference Facility

Each model OI:LOOP serves becomes a set of printed panels in racks in its own hall. Plan (design targets):

| Year | Stage | Panel area | Tokens per second |
|---|---|---|---|
| 2 | Half-billion-parameter model, end to end | ~1.8 m² | 1M |
| 3 | ~100B-class mixture-of-experts pilot through the API | ~500 m² | 1M |
| 4 | First 700B-class mixture-of-experts model in service | ~3,500 m² | 10M |
| 5 | Three 700B-class models from one hall | ~10,500 m² | 30M |

Assumptions: ~5,000 µm² of printed polymer per parameter (range 2,500–10,000); 1 m² glass panels, 40 per rack.

## Stage

Architecture, material choice, energy model and accuracy simulation are complete. Nothing has been fabricated yet. Next: print and measure an 8×8 weight bank, then a 64×64 tile, then run a half-billion-parameter model end to end.

## Connect your AI assistant

MCP server (Streamable HTTP): `https://oiloop.com/mcp`

Tools: `about_oiloop`, `explain_technology`, `get_energy_comparison`, `get_facility_projection(year)`, `join_waitlist(email)`.

```json
{ "mcpServers": { "oiloop": { "url": "https://oiloop.com/mcp" } } }
```

## Follow the build

- LinkedIn: https://www.linkedin.com/company/oiloop/
- X: https://x.com/OpIntLoop
- Instagram: https://www.instagram.com/o.i.l.o.o.p/
- GitHub: https://github.com/OpIntLoop

SYS.ID // PMP-1550 // REV.5 · © 2026 OI:LOOP · Figures are design targets, not measurements.
