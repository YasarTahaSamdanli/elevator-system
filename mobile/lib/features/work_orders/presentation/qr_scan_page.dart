import 'package:flutter/material.dart';
import 'package:mobile_scanner/mobile_scanner.dart';

/// Asansör QR etiketini okutur; okunan ham değerle `Navigator.pop` eder.
/// İptalde `null` döner — çağıran taraf akışı yarıda kesmelidir.
class QrScanPage extends StatefulWidget {
  const QrScanPage({super.key, this.title = 'Asansör QR Kodunu Okutun'});

  final String title;

  @override
  State<QrScanPage> createState() => _QrScanPageState();
}

class _QrScanPageState extends State<QrScanPage> {
  final MobileScannerController _controller = MobileScannerController(
    formats: const [BarcodeFormat.qrCode],
  );

  bool _handled = false;

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  void _onDetect(BarcodeCapture capture) {
    if (_handled) {
      return;
    }

    final value = capture.barcodes
        .map((barcode) => barcode.rawValue)
        .whereType<String>()
        .where((raw) => raw.isNotEmpty)
        .firstOrNull;

    if (value == null) {
      return;
    }

    _handled = true;
    Navigator.of(context).pop(value);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text(widget.title),
        actions: [
          IconButton(
            onPressed: () => _controller.toggleTorch(),
            icon: const Icon(Icons.flashlight_on_outlined),
            tooltip: 'Fener',
          ),
        ],
      ),
      body: Stack(
        fit: StackFit.expand,
        children: [
          MobileScanner(
            controller: _controller,
            onDetect: _onDetect,
            errorBuilder: (context, error, child) => Center(
              child: Padding(
                padding: const EdgeInsets.all(24),
                child: Text(
                  error.errorCode == MobileScannerErrorCode.permissionDenied
                      ? 'Kamera izni verilmedi. Uygulama ayarlarından '
                          'kamera iznini açıp tekrar deneyin.'
                      : 'Kamera başlatılamadı: ${error.errorCode.name}',
                  textAlign: TextAlign.center,
                ),
              ),
            ),
          ),
          // Nişan çerçevesi: teknisyeni etiketi ortalamaya yönlendirir.
          IgnorePointer(
            child: Center(
              child: Container(
                width: 240,
                height: 240,
                decoration: BoxDecoration(
                  border: Border.all(color: Colors.white70, width: 3),
                  borderRadius: BorderRadius.circular(16),
                ),
              ),
            ),
          ),
          Positioned(
            left: 24,
            right: 24,
            bottom: 32,
            child: Text(
              'Kabin içindeki veya makine dairesindeki QR etiketini '
              'çerçeveye hizalayın.',
              textAlign: TextAlign.center,
              style: TextStyle(
                color: Colors.white,
                shadows: [Shadow(blurRadius: 8)],
              ),
            ),
          ),
        ],
      ),
    );
  }
}
