# chatparts

Status: pre-release.

chatparts is a framework-agnostic, buildless PHP 8.4 kit of server-rendered chat and agent
UI components. It mirrors the message-parts model of Vercel AI SDK UI (text, reasoning,
tool, source, file and data parts) so a host app can render, stream and persist agent
conversations without a JavaScript build step or Composer.

## Install by sync

A host keeps its own copy of the kit. From a chatparts checkout:

```sh
scripts/sync-into.sh /path/to/host-plugin
```

This replaces `/path/to/host-plugin/vendor-chatparts/` with `src/`, `assets/`,
`autoload.php`, `LICENSE`, `THIRD_PARTY.md` and `CHECKSUMS`. Files from an older copy are
removed. The copy is staged and checked against `CHECKSUMS` first, and the script exits 1
on a mismatch or an extra file. A failed sync leaves the previous copy as it was. It also refuses to run when `CHECKSUMS` does not match the
source tree. After you change a vendored file, run `scripts/checksums.sh` and commit
`CHECKSUMS`.

The host can re-check its copy at any time:

```sh
cd vendor-chatparts && sha256sum --quiet --strict -c CHECKSUMS
```

Then load the classes:

```php
require __DIR__ . '/vendor-chatparts/autoload.php';
```

Each tagged release also attaches `chatparts-<tag>.zip` with the same files.

## The HostAdapter contract

The kit never names a framework. The host implements `ChatParts\Host\HostAdapter` once and
passes it to every component.

| Method | Returns |
| --- | --- |
| `url(string $route, array $params = []): string` | An app URL for a named route. The host builds and encodes it. |
| `csrfField(): string` | The hidden CSRF field (or fields) as ready HTML. |
| `escape(string $s): string` | Text escaped for HTML element content. |
| `markdown(string $text): string` | Markdown rendered to HTML that is already safe to print. |
| `avatarFor(Participant $p): array` | `['initials' => string, 'tone' => string]` for an avatar. |
| `t(string $s, mixed ...$args): string` | A translated string. Extra args are sprintf placeholders. |
| `assetUrl(string $file): string` | The public URL of a file in `assets/`, for example `icons.svg`. |

## Components

Components live in `ChatParts\View`, extend `Component` and take the host first. Call
`render()` or cast one to a string.

| Component | Constructor arguments after `$host` |
| --- | --- |
| `Conversation` | `array $messages` (list of `Message`, oldest first), `string $pollUrl`, `int $afterId` |
| `MessageBubble` | `Message $m`, `bool $streaming = false` |
| `Response` | `TextPart $part`, `bool $streaming`, `array $sources = []` (list of `SourcePart`) |
| `Reasoning` | `ReasoningPart $part`, `bool $streaming = false` |
| `ToolStrip` | `string $messageId`, `array $tools` (list of `ToolPart`), `array $sources = []` |
| `SourceList` | `array $sources` (list of `SourcePart`) |
| `InlineCitation` | `int $n`, `?SourcePart $source` |
| `Receipt` | `array $metadata` (the message metadata) |
| `Composer` | `string $action`, `array $opts = []` (`placeholder`, `room`, `allowImages`, `canAsk`, `contextUrl`, `stopUrl`) |
| `ContextMeter` | `array $segments` (list of `key`, `label`, `tokens`), `int $window` |
| `MentionPicker` | `array $entries` (list of `kind`, `handle`, `name`, optional `id`) |
| `PresenceList` | `array $groups` (keyed `people`, `agents`, `bots`) |
| `Avatar` | `Participant $p` |
| `Badge` | `Participant $p` |
| `Notice` | `string $level` (`info`, `warning` or `error`), `string $text` |
| `Drawer` | `string $title`, `string $subtitle`, `string $body` (ready HTML) |
| `Icon` | `string $name`, `string $class = 'cp-icon'` |

`Hx::attrs()` builds `hx-*` and `sse-*` attributes from an allow-list. It is a static
helper, not a component.

## Stream protocol

`ChatParts\Stream\SseWriter` writes HTML over server-sent events for htmx-ext-sse. Each
event's data is an HTML fragment. The names are constants on `ChatParts\Stream\StreamEvents`.

| Event | Constant | Carries |
| --- | --- | --- |
| `delta` | `StreamEvents::DELTA` | Appended response text. |
| `reasoning` | `StreamEvents::REASONING` | Appended reasoning text. |
| `parts` | `StreamEvents::PARTS` | Tool, source or file parts, usually out-of-band fragments (see `StreamEvents::oob()`). |
| `final` | `StreamEvents::FINAL` | The finished message. It replaces the streamed draft. |
| `done` | `StreamEvents::DONE` | End of stream. The client closes and does not reconnect. |

Rules for the host:

- Check access, then call `session_write_close()` before the first write. A stream lasts
  many seconds and must not hold the session lock.
- Every response from the stream URL ends with `done`. htmx-ext-sse reconnects whenever
  the source closes any other way, a 204 or a 403 included, forever.
- Answer a spent, unknown or foreign stream id with `refuse()`. It sends HTTP 200 and one
  `done` event. Never answer with 204 or an error status.
- Call `heartbeat()` or `maybeHeartbeat()` while you wait, and `close()` at the end.
  Writes after `close()` or `refuse()` do nothing.

## Content Security Policy

The kit needs no inline script and no `eval`.

- Turn off htmx's eval and script handling with a meta tag:
  `<meta name="htmx-config" content='{"allowEval":false,"allowScriptTags":false}'>`
- Serve `htmx.min.js`, `htmx-ext-sse.js`, `chatparts.js`, `chatparts.css` and `icons.svg`
  from the host's own origin through `assetUrl()`. Nothing loads from a CDN.
- `script-src 'self'` is enough for the kit.
- A named avatar tone (`blue`, `green`, `orange`, `pink`, `purple`, `red`, `slate` or
  `teal`) becomes a `cp-tone-*` class. A hex tone (`#2271b1`) becomes a `style` attribute
  (`--cp-tone`). If your policy blocks style attributes, return named tones from
  `avatarFor()`, or a hex tone falls back to grey.

## Theming

Every rule hangs off a `cp-` class, so the stylesheet is safe on any page. Restyle by
overriding the tokens from `assets/chatparts.css`:

| Token | Use |
| --- | --- |
| `--cp-surface`, `--cp-panel` | Page and panel backgrounds |
| `--cp-text`, `--cp-muted` | Body and secondary text |
| `--cp-border` | Borders |
| `--cp-accent`, `--cp-accent-dark`, `--cp-on-accent` | Accent colour, its hover shade, text on it |
| `--cp-code-bg`, `--cp-code-text`, `--cp-inline-code` | Code blocks and inline code |
| `--cp-radius` | Corner radius |
| `--cp-hover` | Hover wash |
| `--cp-cite-bg` | Citation chip and selected mention |
| `--cp-off` | Disabled send button and the context ring track |
| `--cp-success`, `--cp-warning`, `--cp-error` | Status colours |
| `--cp-shadow` | Floating panels and buttons |
| `--cp-mono` | Monospace font stack |

Dark mode follows the OS (`prefers-color-scheme`). Add `.cp-dark` to any ancestor to force it.

## Third-party code

See `THIRD_PARTY.md` for htmx, htmx-ext-sse and the Lucide icons.
