/// Ortam yapılandırması. Derleme sırasında `--dart-define` ile geçilir:
///
///   flutter run --dart-define=API_BASE_URL=http://192.168.1.20:8000/api/v1
///
/// Varsayılan, Android emülatöründen host makinedeki Laravel'e işaret eder
/// (10.0.2.2 = emülatör loopback).
abstract final class AppConfig {
  static const String apiBaseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: 'http://10.0.2.2:8000/api/v1',
  );
}
