import { useEffect, useRef, useState } from 'react';
import { Html5Qrcode } from 'html5-qrcode';

function QRCodeScanner({ onScanSuccess, onError }) {
  const scannerRef = useRef(null);
  const [scannerReady, setScannerReady] = useState(false);
  const [statusText, setStatusText] = useState('Waiting for camera access...');

  useEffect(() => {
    const containerId = 'html5qr-code-full-region';
    const html5QrCode = new Html5Qrcode(containerId);

    const startScanner = async () => {
      try {
        await Html5Qrcode.getCameras();
        await html5QrCode.start(
          { facingMode: 'environment' },
          {
            fps: 10,
            qrbox: 250,
          },
          (decodedText) => {
            try {
              const data = JSON.parse(decodedText);
              if (data.sessionId && data.subject && data.section && data.date && data.time) {
                onScanSuccess(data);
                setStatusText('QR code scanned successfully.');
              } else {
                onError('Invalid QR code payload.');
                setStatusText('Invalid QR structure.');
              }
            } catch {
              onError('Invalid QR code format.');
              setStatusText('Scanned data is not valid JSON.');
            }
          },
          (errorMessage) => {
            setStatusText('Scanning...');
          }
        );
        scannerRef.current = html5QrCode;
        setScannerReady(true);
      } catch (scanError) {
        onError('Camera access denied or unavailable.');
        setStatusText('Camera permission needed.');
      }
    };

    startScanner();

    return () => {
      if (scannerRef.current) {
        scannerRef.current.stop().catch(() => {});
      }
    };
  }, [onError, onScanSuccess]);

  return (
    <div className="scanner-card">
      <div id="html5qr-code-full-region" className="scanner-window" />
      <p className="scanner-status">{statusText}</p>
      {!scannerReady && <div className="loader" />}
    </div>
  );
}

export default QRCodeScanner;
