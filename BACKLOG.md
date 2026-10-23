# Backlog

Minor QA follow-ups planned for **0.1.6**. These are not blockers for 0.1.5.

## 0.1.6

- **CSV formula prefix:** a cell that starts with a space followed by `=` is not neutralized. Negative numbers currently receive a leading apostrophe; keep real negatives as numbers and still block formula-like values (`=`, `+`, `@`, tab, CR, and space-then-formula).
- **Activate after upload:** the activation form shows plugin file paths instead of plugin names, and it accepts any already-installed plugin. Limit activation to the plugins that were just uploaded, and display their names.
- **Download nonce:** the same download token is reused for every item. Use one nonce per item.
