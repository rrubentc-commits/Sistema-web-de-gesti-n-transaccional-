(() => {
    const connectButton = document.getElementById('rfid-connect');
    const disconnectButton = document.getElementById('rfid-disconnect');
    const status = document.getElementById('rfid-status');
    const csrfToken = document.querySelector('meta[name="rfid-csrf-token"]')?.content;
    if (!connectButton || !disconnectButton || !status) return;

    let port = null;
    let reader = null;
    let reading = false;

    const setStatus = (message, isError = false) => {
        status.textContent = message;
        status.className = isError ? 'text-danger' : 'text-muted';
    };

    async function submitUid(uid) {
        const response = await fetch(window.location.pathname, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken
            },
            body: JSON.stringify({ action: 'registrar_ingreso', uid })
        });
        const result = await response.json();
        if (!response.ok || !result.success) throw new Error(result.message || 'No se pudo registrar el UID.');
        const label = result.cisterna
            ? `${result.cisterna.codigo} (${result.cisterna.placa})`
            : 'UID no asociado a una cisterna';
        setStatus(`Ingreso registrado: ${uid} - ${label}`);
    }

    connectButton.addEventListener('click', async () => {
        if (!('serial' in navigator)) {
            setStatus('Web Serial no está disponible. Usa Chrome o Edge en localhost.', true);
            return;
        }
        try {
            port = await navigator.serial.requestPort();
            await port.open({ baudRate: Number(connectButton.dataset.baud) });
            reader = port.readable.getReader();
            reading = true;
            connectButton.disabled = true;
            disconnectButton.disabled = false;
            setStatus(`Lector conectado. Selecciona el puerto ${connectButton.dataset.port} en el selector del navegador.`);

            const decoder = new TextDecoder();
            let pending = '';
            while (reading) {
                const { value, done } = await reader.read();
                if (done) break;
                pending += decoder.decode(value, { stream: true });
                const lines = pending.split(/\r?\n/);
                pending = lines.pop() || '';
                for (const line of lines) {
                    try {
                        const payload = line.trim();
                        if (!payload) continue;
                        let uid = payload;
                        if (payload.startsWith('{')) {
                            const data = JSON.parse(payload);
                            uid = data.uid;
                        }
                        if (typeof uid !== 'string' || !uid.trim()) {
                            throw new Error('El lector no envió un UID válido.');
                        }
                        await submitUid(uid);
                    } catch (error) {
                        setStatus(`No se pudo procesar la lectura: ${error.message}`, true);
                    }
                }
            }
        } catch (error) {
            if (error.name !== 'NotFoundError') setStatus(`No se pudo conectar: ${error.message}`, true);
            await disconnect();
        }
    });

    async function disconnect() {
        reading = false;
        try {
            if (reader) {
                await reader.cancel();
                reader.releaseLock();
            }
            if (port?.readable) await port.close();
        } catch (error) {
            setStatus(`Error al desconectar: ${error.message}`, true);
        } finally {
            reader = null;
            port = null;
            connectButton.disabled = false;
            disconnectButton.disabled = true;
            if (!status.classList.contains('text-danger')) setStatus('Lector desconectado');
        }
    }

    disconnectButton.addEventListener('click', disconnect);

    if (navigator.serial) {
        navigator.serial.addEventListener('disconnect', () => {
            disconnect();
            setStatus('El lector se desconectó del equipo.', true);
        });
    }

})();