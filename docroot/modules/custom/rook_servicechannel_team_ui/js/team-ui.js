(function (Drupal, drupalSettings, once) {
  'use strict';

  const DEFAULT_SETTINGS = {
    pinLookupUrl: '/api/client/1/pinlookup',
    sessionStatusUrl: '/api/client/1/sessionstatus',
    requestShellUrl: '/api/client/1/requestshell',
    gatewayBaseUrl: '',
    gatewayTerminalPath: '/gateway/terminal',
  };

  Drupal.behaviors.rookServicechannelTeamUi = {
    attach(context) {
      const runtimeSettings = {
        ...DEFAULT_SETTINGS,
        ...(drupalSettings.rookServicechannelTeamUi || {}),
      };

      once('rook-servicechannel-team-ui', '[data-rook-team-ui]', context).forEach((root) => {
        initializeApp(root, runtimeSettings);
      });
    },
  };

  function initializeApp(root, runtimeSettings) {
    const elements = {
      form: root.querySelector('[data-rook-pin-form]'),
      pinInput: root.querySelector('[data-rook-pin-input]'),
      pinSubmit: root.querySelector('[data-rook-pin-submit]'),
      refreshButton: root.querySelector('[data-rook-status-refresh]'),
      openTerminalButton: root.querySelector('[data-rook-open-terminal]'),
      closeTerminalButton: root.querySelector('[data-rook-close-terminal]'),
      apiState: root.querySelector('[data-rook-api-state]'),
      sessionStatus: root.querySelector('[data-rook-session-status]'),
      terminalState: root.querySelector('[data-rook-terminal-state]'),
      message: root.querySelector('[data-rook-ui-message]'),
      terminalContainer: root.querySelector('[data-rook-terminal]'),
    };

    if (!elements.form || !elements.pinInput || !elements.terminalContainer) {
      return;
    }

    const state = {
      settings: runtimeSettings,
      pin: '',
      sessionKnown: false,
      socket: null,
      authorized: false,
      pendingToken: '',
      disconnectReason: 'Disconnected',
      decoder: new TextDecoder(),
      terminal: null,
      fitAddon: null,
      resizeTimeout: null,
    };

    initializeTerminal(state, elements);
    bindUiEvents(state, elements);
    syncButtons(state, elements);
  }

  function initializeTerminal(state, elements) {
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
    terminal.open(elements.terminalContainer);
    terminal.writeln('RooK browser terminal ready.');
    terminal.writeln('Use "Open terminal" to request a grant and connect to the gateway.');
    terminal.writeln('');

    state.terminal = terminal;
    state.fitAddon = fitAddon;

    requestAnimationFrame(() => {
      fitAddon.fit();
      sendResize(state);
    });

    terminal.onData((data) => {
      if (!state.socket || state.socket.readyState !== WebSocket.OPEN) {
        return;
      }

      if (!state.authorized) {
        return;
      }

      sendFrame(state.socket, {
        type: 'input',
        data,
      });
    });

    window.addEventListener('resize', () => {
      window.clearTimeout(state.resizeTimeout);
      state.resizeTimeout = window.setTimeout(() => {
        fitAddon.fit();
        sendResize(state);
      }, 120);
    });
  }

  function bindUiEvents(state, elements) {
    elements.pinInput.addEventListener('input', (event) => {
      state.pin = event.target.value.trim();
      state.sessionKnown = false;
      setSessionStatus(elements, 'Unknown');
      clearMessage(elements);
      syncButtons(state, elements);
    });

    elements.form.addEventListener('submit', async (event) => {
      event.preventDefault();
      await runSessionAction(state, elements, state.settings.pinLookupUrl, 'Coupling session');
    });

    elements.refreshButton.addEventListener('click', async () => {
      await runSessionAction(state, elements, state.settings.sessionStatusUrl, 'Refreshing session status');
    });

    elements.openTerminalButton.addEventListener('click', async () => {
      clearMessage(elements);
      setApiState(elements, 'Requesting terminal grant');

      try {
        const response = await postJson(state.settings.requestShellUrl, { pin: requirePin(state) });
        const token = response.grant && typeof response.grant.token === 'string' ? response.grant.token : '';

        if (token === '') {
          throw new Error('The backend returned an empty terminal grant token.');
        }

        connectGateway(state, elements, token);
        setApiState(elements, 'Terminal grant issued');
      }
      catch (error) {
        setApiState(elements, 'Terminal grant failed');
        showMessage(elements, getErrorMessage(error));
      }
      finally {
        syncButtons(state, elements);
      }
    });

    elements.closeTerminalButton.addEventListener('click', () => {
      disconnectGateway(state, elements, 'Disconnected by user');
    });
  }

  async function runSessionAction(state, elements, url, actionLabel) {
    clearMessage(elements);
    setApiState(elements, actionLabel);

    try {
      const response = await postJson(url, { pin: requirePin(state) });
      state.sessionKnown = Boolean(response.session && response.session.status);
      setSessionStatus(elements, state.sessionKnown ? response.session.status : 'Unknown');
      setApiState(elements, 'Ready');
    }
    catch (error) {
      state.sessionKnown = false;
      setSessionStatus(elements, 'Unknown');
      setApiState(elements, 'Request failed');
      showMessage(elements, getErrorMessage(error));
    }
    finally {
      syncButtons(state, elements);
    }
  }

  function requirePin(state) {
    const pin = state.pin.trim();

    if (pin === '') {
      throw new Error('Enter a session PIN first.');
    }

    return pin;
  }

  async function postJson(url, payload) {
    const response = await fetch(url, {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
      },
      body: JSON.stringify(payload),
      credentials: 'same-origin',
    });

    let decoded = {};
    try {
      decoded = await response.json();
    }
    catch (error) {
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

  function connectGateway(state, elements, token) {
    disconnectGateway(state, elements, 'Disconnected');

    const target = buildGatewayTarget(state.settings);
    const socket = new WebSocket(target.url);

    socket.binaryType = 'arraybuffer';
    state.socket = socket;
    state.authorized = false;
    state.pendingToken = token;
    state.disconnectReason = 'Disconnected';

    setTerminalState(elements, 'Connecting');
    state.terminal.focus();
    state.terminal.writeln(`[gateway] Opening ${target.url}`);

    socket.addEventListener('open', () => {
      setTerminalState(elements, 'Authorizing');
      sendFrame(socket, {
        type: 'authorize',
        token: state.pendingToken,
      });
      syncButtons(state, elements);
    });

    socket.addEventListener('message', async (event) => {
      await handleSocketMessage(state, elements, event.data);
    });

    socket.addEventListener('error', () => {
      showMessage(elements, 'The browser terminal failed to communicate with the gateway.');
    });

    socket.addEventListener('close', (event) => {
      state.socket = null;
      state.authorized = false;
      state.pendingToken = '';

      const message = event.reason || state.disconnectReason || 'Disconnected';
      setTerminalState(elements, message);
      state.terminal.writeln(`[gateway] ${message}`);
      syncButtons(state, elements);
    });

    syncButtons(state, elements);
  }

  function disconnectGateway(state, elements, reason) {
    state.disconnectReason = reason || 'Disconnected';
    state.authorized = false;
    state.pendingToken = '';

    if (!state.socket) {
      setTerminalState(elements, state.disconnectReason);
      syncButtons(state, elements);
      return;
    }

    const socket = state.socket;
    state.socket = null;

    if (socket.readyState === WebSocket.OPEN || socket.readyState === WebSocket.CONNECTING) {
      socket.close();
    }

    setTerminalState(elements, state.disconnectReason);
    syncButtons(state, elements);
  }

  function buildGatewayTarget(settings) {
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

    return {
      url: url.toString(),
    };
  }

  function normalizePath(path) {
    if (!path || path === '/') {
      return '/gateway/terminal';
    }

    return path.startsWith('/') ? path : `/${path}`;
  }

  async function handleSocketMessage(state, elements, payload) {
    if (typeof payload === 'string') {
      handleTextPayload(state, elements, payload);
      return;
    }

    if (payload instanceof ArrayBuffer) {
      state.terminal.write(state.decoder.decode(payload));
      return;
    }

    if (payload instanceof Blob) {
      const buffer = await payload.arrayBuffer();
      state.terminal.write(state.decoder.decode(buffer));
    }
  }

  function handleTextPayload(state, elements, payload) {
    let decoded = null;

    try {
      decoded = JSON.parse(payload);
    }
    catch (error) {
      state.terminal.write(payload);
      return;
    }

    if (!decoded || typeof decoded !== 'object') {
      state.terminal.write(payload);
      return;
    }

    if (decoded.type === 'output') {
      const output = pickString(decoded, ['data', 'text', 'payload', 'chunk', 'content']);
      if (output !== null) {
        state.terminal.write(output);
      }
      return;
    }

    if (decoded.type === 'authorized') {
      state.authorized = true;
      state.pendingToken = '';
      setTerminalState(elements, 'Connected');
      clearMessage(elements);
      state.fitAddon.fit();
      sendResize(state);
      syncButtons(state, elements);
      return;
    }

    if (decoded.type === 'error' || decoded.type === 'close') {
      const message = pickString(decoded, ['message', 'detail', 'error']) || `${decoded.type} received from gateway.`;
      showMessage(elements, message);
      setTerminalState(elements, decoded.type === 'close' ? 'Closed' : 'Gateway error');
      state.authorized = false;

      if (decoded.type === 'close') {
        disconnectGateway(state, elements, message);
      }

      return;
    }

    const fallback = pickString(decoded, ['data', 'text', 'payload', 'chunk', 'content']);
    if (fallback !== null) {
      state.terminal.write(fallback);
    }
  }

  function pickString(payload, keys) {
    for (const key of keys) {
      if (typeof payload[key] === 'string' && payload[key] !== '') {
        return payload[key];
      }
    }

    return null;
  }

  function sendResize(state) {
    if (!state.socket || state.socket.readyState !== WebSocket.OPEN || !state.terminal || !state.authorized) {
      return;
    }

    sendFrame(state.socket, {
      type: 'resize',
      cols: state.terminal.cols,
      rows: state.terminal.rows,
    });
  }

  function sendFrame(socket, payload) {
    socket.send(JSON.stringify(payload));
  }

  function syncButtons(state, elements) {
    const hasPin = state.pin !== '';
    const isConnected = state.socket && state.socket.readyState === WebSocket.OPEN && state.authorized;
    const isConnecting = state.socket && state.socket.readyState === WebSocket.CONNECTING;
    const isAuthorizing = state.socket && state.socket.readyState === WebSocket.OPEN && !state.authorized;

    elements.pinSubmit.disabled = !hasPin;
    elements.refreshButton.disabled = !hasPin;
    elements.openTerminalButton.disabled = !hasPin || !state.sessionKnown || isConnected || isConnecting || isAuthorizing;
    elements.closeTerminalButton.disabled = !(isConnected || isConnecting || isAuthorizing);
  }

  function setApiState(elements, value) {
    elements.apiState.textContent = value;
  }

  function setSessionStatus(elements, value) {
    elements.sessionStatus.textContent = value;
  }

  function setTerminalState(elements, value) {
    elements.terminalState.textContent = value;
  }

  function showMessage(elements, value) {
    elements.message.textContent = value;
    elements.message.hidden = false;
  }

  function clearMessage(elements) {
    elements.message.textContent = '';
    elements.message.hidden = true;
  }

  function getErrorMessage(error) {
    if (error instanceof Error) {
      return error.message;
    }

    return 'An unexpected error occurred.';
  }
})(Drupal, drupalSettings, once);
