# Data Model: Exceptions Reference and Troubleshooting Pages

Content model only; no persisted data.

## Exception reference entry (one H2 per class)

| Field | Rule |
|---|---|
| Heading | `## \`ClassName\`` (short class name, in code formatting); exactly one per file in `src/Exceptions/` |
| Extends | Parent class as in source (`RuntimeException` / `Erecht24ApiException`) |
| Thrown when | Conditions and call site, as in [research.md](research.md) |
| Handling | What a caller/operator should do; names the command exit code where relevant |
| Public properties | Listed where the class has them (`status`, `apiMessage`, `debug`, `clients`, `clientId`) |

## Troubleshooting entry (one H2 per problem)

| Field | Rule |
|---|---|
| Heading | Symptom in plain words (observable behavior, not a class name) |
| Symptom | Label `**Symptom:**` (DE `**Symptom:**`) |
| Cause | Label `**Cause:**` (DE `**Ursache:**`), may list several causes |
| Fix | Label `**Fix:**` (DE `**Lösung:**`), commands in code blocks; may link to reference/guide pages |

Order inside an entry is fixed: Symptom, Cause, Fix. EN and DE have the same entries in the same order.
