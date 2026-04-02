import React, { FormEvent, useEffect, useMemo, useRef, useState } from 'react';
import { createRoot } from 'react-dom/client';

type RuntimeSettings = {
  pinLookupUrl: string;
  sessionStatusUrl: string;
  requestShellUrl: string;
  gatewayBaseUrl: string;
  gatewayTerminalPath: string;
};

type JsonValue = null | boolean | number | string | JsonValue[] | { [key: string]: JsonValue };
type JsonObject = { [key: string]: JsonValue };

type DrupalBehaviorHost = {
  behaviors: Record<string, { attach(context: ParentNode): void }>;
};

type TerminalInstance = {
  cols: number;
  rows: number;
  loadAddon(addon: FitAddonInstance): void;
  open(element: HTMLElement): void;
  writeln(value: string): void;
  write(value: string): void;
  focus(): void;
  onData(callback: (value: string) => void): void;
};

type FitAddonInstance = {
  fit(): void;
};

type WebSocketTarget = {
  url: string;
};

declare const Drupal: DrupalBehaviorHost;
declare const drupalSettings: { rookServicechannelTeamUi?: Partial<RuntimeSettings> };
declare const once: (id: string, selector: string, context?: ParentNode) => Element[];
declare const Terminal: new (options: Record<string, unknown>) => TerminalInstance;
declare const FitAddon: { FitAddon: new () => FitAddonInstance };

const DEFAULT_SETTINGS: RuntimeSettings = {
  pinLookupUrl: '/api/client/1/pinlookup',
  sessionStatusUrl: '/api/client/1/sessionstatus',
  requestShellUrl: '/api/client/1/requestshell',
  gatewayBaseUrl: '',
  gatewayTerminalPath: '/gateway/terminal',
};
const TERMINAL_VIEWPORT_MARGIN = 24;
const TERMINAL_LAYOUT_SETTLE_DELAY = 180;

function TeamUiApp({ settings }: { settings: RuntimeSettings }): React.JSX.Element {
  const [pin, setPin] = useState('');
  const [sessionKnown, setSessionKnown] = useState(false);
  const [apiState, setApiState] = useState('Idle');
  const [sessionStatus, setSessionStatus] = useState('Unknown');
  const [terminalState, setTerminalState] = useState('Disconnected');
  const [message, setMessage] = useState('');
  const [debugOpen, setDebugOpen] = useState(false);

  const terminalElementRef = useRef<HTMLDivElement | null>(null);
  const terminalShellRef = useRef<HTMLDivElement | null>(null);
  const terminalRef = useRef<TerminalInstance | null>(null);
  const fitAddonRef = useRef<FitAddonInstance | null>(null);
  const socketRef = useRef<WebSocket | null>(null);
  const authorizedRef = useRef(false);
  const pendingTokenRef = useRef('');
  const disconnectReasonRef = useRef('Disconnected');
  const decoderRef = useRef(new TextDecoder());
  const layoutFrameRef = useRef<number | null>(null);
  const layoutSettleTimeoutRef = useRef<number | null>(null);

  const gatewayTarget = useMemo(() => {
    try {
      return buildGatewayTarget(settings);
    }
    catch (error) {
      return {
        url: getErrorMessage(error),
      };
    }
  }, [settings]);

  const syncTerminalLayout = () => {
    const shell = terminalShellRef.current;
    const terminal = terminalRef.current;
    const fitAddon = fitAddonRef.current;

    if (!shell || !terminal || !fitAddon) {
      return;
    }

    const shellRect = shell.getBoundingClientRect();
    const viewportHeight = window.visualViewport?.height ?? window.innerHeight;
    const availableHeight = Math.max(Math.floor(viewportHeight - Math.max(shellRect.top, 0) - TERMINAL_VIEWPORT_MARGIN), 0);
    const ratioHeight = Math.max(Math.round(shellRect.width * 0.75), 0);
    const nextHeight = Math.min(availableHeight, ratioHeight);

    shell.style.setProperty('--rook-terminal-height', `${nextHeight}px`);

    if (nextHeight <= 0) {
      return;
    }

    fitAddon.fit();
    sendResize(socketRef.current, terminal, authorizedRef.current);
  };

  const scheduleTerminalLayoutSync = () => {
    if (layoutFrameRef.current !== null) {
      return;
    }

    layoutFrameRef.current = window.requestAnimationFrame(() => {
      layoutFrameRef.current = null;
      syncTerminalLayout();
    });
  };

  useEffect(() => {
    const terminalElement = terminalElementRef.current;
    const terminalShell = terminalShellRef.current;

    if (!terminalElement || !terminalShell || terminalRef.current) {
      return;
    }

    const terminal = new Terminal({
      convertEol: true,
      cursorBlink: true,
      fontSize: 14,
      fontFamily: 'ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace',
      scrollback: 3000,
      theme: {
        background: '#0f172a',
        foreground: '#e2e8f0',
        cursor: '#38bdf8',
        black: '#0f172a',
      },
    });
    const fitAddon = new FitAddon.FitAddon();

    terminal.loadAddon(fitAddon);
    terminal.open(terminalElement);
    terminal.writeln('RooK browser terminal ready.');
    terminal.writeln('Open the terminal after linking a support session.');
    terminal.writeln('');

    terminal.onData((data) => {
      const socket = socketRef.current;
      if (!socket || socket.readyState !== WebSocket.OPEN || !authorizedRef.current) {
        return;
      }

      sendFrame(socket, {
        type: 'input',
        data,
      });
    });

    terminalRef.current = terminal;
    fitAddonRef.current = fitAddon;

    scheduleTerminalLayoutSync();
    layoutSettleTimeoutRef.current = window.setTimeout(scheduleTerminalLayoutSync, TERMINAL_LAYOUT_SETTLE_DELAY);

    const onViewportChange = () => {
      scheduleTerminalLayoutSync();
    };

    const resizeObserver = new ResizeObserver(() => {
      scheduleTerminalLayoutSync();
    });
    resizeObserver.observe(terminalShell);

    window.addEventListener('resize', onViewportChange);
    window.addEventListener('scroll', onViewportChange, { passive: true });
    window.visualViewport?.addEventListener('resize', onViewportChange);
    window.visualViewport?.addEventListener('scroll', onViewportChange);

    return () => {
      resizeObserver.disconnect();
      window.removeEventListener('resize', onViewportChange);
      window.removeEventListener('scroll', onViewportChange);
      window.visualViewport?.removeEventListener('resize', onViewportChange);
      window.visualViewport?.removeEventListener('scroll', onViewportChange);

      if (layoutFrameRef.current !== null) {
        window.cancelAnimationFrame(layoutFrameRef.current);
      }

      if (layoutSettleTimeoutRef.current !== null) {
        window.clearTimeout(layoutSettleTimeoutRef.current);
      }
    };
  }, []);

  useEffect(() => {
    scheduleTerminalLayoutSync();
  }, [debugOpen]);

  const clearMessage = () => {
    setMessage('');
  };

  const disconnectGateway = (reason: string) => {
    disconnectReasonRef.current = reason || 'Disconnected';
    authorizedRef.current = false;
    pendingTokenRef.current = '';

    const socket = socketRef.current;
    socketRef.current = null;

    if (socket && (socket.readyState === WebSocket.OPEN || socket.readyState === WebSocket.CONNECTING)) {
      socket.close();
    }

    setTerminalState(disconnectReasonRef.current);
  };

  const runSessionAction = async (url: string, actionLabel: string) => {
    clearMessage();
    setApiState(actionLabel);

    try {
      const response = await postJson(url, { pin: requirePin(pin) });
      const nextStatus = getSessionStatusFromResponse(response);

      setSessionKnown(nextStatus !== null);
      setSessionStatus(nextStatus ?? 'Unknown');
      setApiState('Ready');
    }
    catch (error) {
      setSessionKnown(false);
      setSessionStatus('Unknown');
      setApiState('Request failed');
      setMessage(getErrorMessage(error));
    }
  };

  const connectGateway = async (token: string) => {
    const terminal = terminalRef.current;
    if (!terminal) {
      throw new Error('The browser terminal could not be initialized.');
    }

    disconnectGateway('Disconnected');

    const target = buildGatewayTarget(settings);
    const socket = new WebSocket(target.url);

    socket.binaryType = 'arraybuffer';
    socketRef.current = socket;
    authorizedRef.current = false;
    pendingTokenRef.current = token;
    disconnectReasonRef.current = 'Disconnected';

    setTerminalState('Connecting');
    terminal.focus();
    terminal.writeln(`[gateway] Opening ${target.url}`);

    socket.addEventListener('open', () => {
      setTerminalState('Authorizing');
      sendFrame(socket, {
        type: 'authorize',
        token: pendingTokenRef.current,
      });
    });

    socket.addEventListener('message', async (event) => {
      await handleSocketMessage(
        event.data,
        terminal,
        () => {
          authorizedRef.current = true;
          pendingTokenRef.current = '';
          setTerminalState('Connected');
          clearMessage();
          syncTerminalLayout();
        },
        (nextMessage) => {
          setMessage(nextMessage);
        },
        (nextState) => {
          setTerminalState(nextState);
        },
        disconnectGateway,
      );
    });

    socket.addEventListener('error', () => {
      setMessage('The browser terminal failed to communicate with the gateway.');
    });

    socket.addEventListener('close', (event) => {
      socketRef.current = null;
      authorizedRef.current = false;
      pendingTokenRef.current = '';

      const nextMessage = event.reason || disconnectReasonRef.current || 'Disconnected';
      setTerminalState(nextMessage);
      terminal.writeln(`[gateway] ${nextMessage}`);
    });
  };

  const isConnected = socketRef.current?.readyState === WebSocket.OPEN && authorizedRef.current;
  const isConnecting = socketRef.current?.readyState === WebSocket.CONNECTING;
  const isAuthorizing = socketRef.current?.readyState === WebSocket.OPEN && !authorizedRef.current;

  return (
    <section className="rook-team-ui">
      <header className="rook-team-ui__header">
        <p className="rook-team-ui__eyebrow">Service workspace</p>
        <h1 className="rook-team-ui__title">RooK Service Channel</h1>
        <p className="rook-team-ui__subtitle">
          Link a support session by PIN and open the browser terminal against the configured gateway.
        </p>
      </header>

      <div className="rook-team-ui__stack">
        <section className="rook-team-ui__card rook-team-ui__card--controls">
          <div className="rook-team-ui__section-head">
            <div>
              <h2 className="rook-team-ui__section-title">Session controls</h2>
              <p className="rook-team-ui__hint">PIN lookup, status refresh and terminal actions stay in one compact control block.</p>
            </div>
            <button
              className="rook-team-ui__info-button"
              type="button"
              aria-expanded={debugOpen}
              onClick={() => setDebugOpen((current) => !current)}
            >
              i
            </button>
          </div>

          <form
            className="rook-team-ui__form"
            onSubmit={async (event: FormEvent<HTMLFormElement>) => {
              event.preventDefault();
              await runSessionAction(settings.pinLookupUrl, 'Linking session');
            }}
          >
            <label className="rook-team-ui__label" htmlFor="rook-team-ui-pin">Session PIN</label>
            <input
              id="rook-team-ui-pin"
              className="rook-team-ui__input"
              type="text"
              autoComplete="off"
              inputMode="numeric"
              value={pin}
              onChange={(event) => {
                setPin(event.currentTarget.value.trim());
                setSessionKnown(false);
                setSessionStatus('Unknown');
                clearMessage();
              }}
            />

            <div className="rook-team-ui__actions">
              <button className="button button--primary" type="submit" disabled={pin === ''}>Link session</button>
              <button
                className="button"
                type="button"
                disabled={pin === ''}
                onClick={async () => {
                  await runSessionAction(settings.sessionStatusUrl, 'Refreshing session status');
                }}
              >
                Refresh status
              </button>
              <button
                className="button button--primary"
                type="button"
                disabled={pin === '' || !sessionKnown || Boolean(isConnected) || Boolean(isConnecting) || Boolean(isAuthorizing)}
                onClick={async () => {
                  clearMessage();
                  setApiState('Requesting terminal grant');

                  try {
                    const response = await postJson(settings.requestShellUrl, { pin: requirePin(pin) });
                    const grant = response.grant;
                    const token = grant && typeof grant === 'object' && !Array.isArray(grant) && typeof grant.token === 'string'
                      ? grant.token
                      : '';

                    if (token === '') {
                      throw new Error('The backend returned an empty terminal grant token.');
                    }

                    await connectGateway(token);
                    setApiState('Terminal grant issued');
                  }
                  catch (error) {
                    setApiState('Terminal grant failed');
                    setMessage(getErrorMessage(error));
                  }
                }}
              >
                Open terminal
              </button>
              <button
                className="button"
                type="button"
                disabled={!(isConnected || isConnecting || isAuthorizing)}
                onClick={() => disconnectGateway('Disconnected by user')}
              >
                Disconnect terminal
              </button>
            </div>
          </form>

          {message !== '' && <div className="rook-team-ui__message" role="alert">{message}</div>}

          {debugOpen && (
            <section className="rook-team-ui__debug" aria-label="Runtime diagnostics">
              <dl className="rook-team-ui__facts">
                <div>
                  <dt>API state</dt>
                  <dd>{apiState}</dd>
                </div>
                <div>
                  <dt>Session status</dt>
                  <dd>{sessionStatus}</dd>
                </div>
                <div>
                  <dt>Terminal state</dt>
                  <dd>{terminalState}</dd>
                </div>
                <div>
                  <dt>Linked PIN</dt>
                  <dd>{pin || '—'}</dd>
                </div>
                <div>
                  <dt>Gateway target</dt>
                  <dd>{gatewayTarget.url}</dd>
                </div>
              </dl>
            </section>
          )}
        </section>

        <section className="rook-team-ui__card rook-team-ui__card--terminal">
          <div className="rook-team-ui__section-head rook-team-ui__section-head--terminal">
            <div>
              <h2 className="rook-team-ui__section-title">Browser terminal</h2>
              <p className="rook-team-ui__hint">
                The UI reuses the client API and then sends the terminal grant as the first
                <code> authorize </code>
                message after the WebSocket upgrade succeeds.
              </p>
            </div>
            <div className="rook-team-ui__status-chip">{terminalState}</div>
          </div>

          <div className="rook-team-ui__terminal-shell" ref={terminalShellRef}>
            <div className="rook-team-ui__terminal" ref={terminalElementRef} />
          </div>
        </section>
      </div>
    </section>
  );
}

async function handleSocketMessage(
  payload: string | ArrayBuffer | Blob,
  terminal: TerminalInstance,
  onAuthorized: () => void,
  onMessage: (value: string) => void,
  onState: (value: string) => void,
  disconnectGateway: (reason: string) => void,
): Promise<void> {
  if (typeof payload === 'string') {
    handleTextPayload(payload, terminal, onAuthorized, onMessage, onState, disconnectGateway);
    return;
  }

  if (payload instanceof ArrayBuffer) {
    terminal.write(new TextDecoder().decode(payload));
    return;
  }

  const buffer = await payload.arrayBuffer();
  terminal.write(new TextDecoder().decode(buffer));
}

function handleTextPayload(
  payload: string,
  terminal: TerminalInstance,
  onAuthorized: () => void,
  onMessage: (value: string) => void,
  onState: (value: string) => void,
  disconnectGateway: (reason: string) => void,
): void {
  let decoded: JsonObject | null = null;

  try {
    const parsed = JSON.parse(payload) as JsonValue;
    decoded = typeof parsed === 'object' && parsed !== null && !Array.isArray(parsed) ? parsed as JsonObject : null;
  }
  catch {
    terminal.write(payload);
    return;
  }

  if (!decoded) {
    terminal.write(payload);
    return;
  }

  if (decoded.type === 'output') {
    const output = pickString(decoded, ['data', 'text', 'payload', 'chunk', 'content']);
    if (output !== null) {
      terminal.write(output);
    }
    return;
  }

  if (decoded.type === 'authorized') {
    onAuthorized();
    return;
  }

  if (decoded.type === 'error' || decoded.type === 'close') {
    const nextMessage = pickString(decoded, ['message', 'detail', 'error']) ?? `${decoded.type} received from gateway.`;
    onMessage(nextMessage);
    onState(decoded.type === 'close' ? 'Closed' : 'Gateway error');

    if (decoded.type === 'close') {
      disconnectGateway(nextMessage);
    }

    return;
  }

  const fallback = pickString(decoded, ['data', 'text', 'payload', 'chunk', 'content']);
  if (fallback !== null) {
    terminal.write(fallback);
  }
}

async function postJson(url: string, payload: JsonObject): Promise<JsonObject> {
  const response = await fetch(url, {
    method: 'POST',
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
    },
    body: JSON.stringify(payload),
    credentials: 'same-origin',
  });

  let decoded: JsonObject = {};

  try {
    const parsed = JSON.parse(await response.text()) as JsonValue;
    decoded = typeof parsed === 'object' && parsed !== null && !Array.isArray(parsed) ? parsed as JsonObject : {};
  }
  catch {
    decoded = {};
  }

  if (!response.ok) {
    const message = typeof decoded.message === 'string' && decoded.message !== ''
      ? decoded.message
      : `Request failed with HTTP ${response.status}.`;

    throw new Error(message);
  }

  return decoded;
}

function requirePin(pin: string): string {
  const nextPin = pin.trim();
  if (nextPin === '') {
    throw new Error('Enter a session PIN first.');
  }

  return nextPin;
}

function getSessionStatusFromResponse(response: JsonObject): string | null {
  const session = response.session;
  if (!session || typeof session !== 'object' || Array.isArray(session)) {
    return null;
  }

  return typeof session.status === 'string' ? session.status : null;
}

function buildGatewayTarget(settings: RuntimeSettings): WebSocketTarget {
  const origin = settings.gatewayBaseUrl || window.location.origin;
  const url = new URL(origin, window.location.origin);

  if (url.protocol === 'http:') {
    url.protocol = 'ws:';
  }
  else if (url.protocol === 'https:') {
    url.protocol = 'wss:';
  }
  else if (url.protocol !== 'ws:' && url.protocol !== 'wss:') {
    throw new Error('The configured gateway URL must start with http://, https://, ws:// or wss://.');
  }

  url.pathname = normalizePath(settings.gatewayTerminalPath);
  url.search = '';
  url.hash = '';

  return { url: url.toString() };
}

function normalizePath(path: string): string {
  if (!path || path === '/') {
    return '/gateway/terminal';
  }

  return path.startsWith('/') ? path : `/${path}`;
}

function pickString(payload: JsonObject, keys: string[]): string | null {
  for (const key of keys) {
    if (typeof payload[key] === 'string' && payload[key] !== '') {
      return payload[key] as string;
    }
  }

  return null;
}

function sendResize(socket: WebSocket | null, terminal: TerminalInstance, authorized: boolean): void {
  if (!socket || socket.readyState !== WebSocket.OPEN || !authorized) {
    return;
  }

  sendFrame(socket, {
    type: 'resize',
    columns: terminal.cols,
    rows: terminal.rows,
  });
}

function sendFrame(socket: WebSocket, payload: JsonObject): void {
  socket.send(JSON.stringify(payload));
}

function getErrorMessage(error: unknown): string {
  if (error instanceof Error) {
    return error.message;
  }

  return 'An unexpected error occurred.';
}

Drupal.behaviors.rookServicechannelTeamUi = {
  attach(context: ParentNode): void {
    const runtimeSettings: RuntimeSettings = {
      ...DEFAULT_SETTINGS,
      ...(drupalSettings.rookServicechannelTeamUi ?? {}),
    };

    once('rook-servicechannel-team-ui', '[data-rook-team-ui]', context).forEach((element) => {
      createRoot(element as HTMLElement).render(<TeamUiApp settings={runtimeSettings} />);
    });
  },
};
