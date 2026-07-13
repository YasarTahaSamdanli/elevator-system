import 'package:dio/dio.dart';

/// Backend'in §12 hata zarfını (`{success, message, error: {code, details}}`)
/// veya ağ seviyesindeki hataları tek tipte temsil eder.
class ApiException implements Exception {
  ApiException({
    required this.message,
    this.code = 'UNKNOWN',
    this.statusCode,
    this.details = const {},
  });

  factory ApiException.fromDio(DioException exception) {
    final response = exception.response;
    final body = response?.data;

    if (body is Map<String, dynamic> && body['error'] is Map<String, dynamic>) {
      final error = body['error'] as Map<String, dynamic>;

      return ApiException(
        message: body['message'] as String? ?? 'Beklenmeyen bir hata oluştu.',
        code: error['code'] as String? ?? 'UNKNOWN',
        statusCode: response?.statusCode,
        details: (error['details'] as Map?)?.cast<String, dynamic>() ?? const {},
      );
    }

    return ApiException(
      message: switch (exception.type) {
        DioExceptionType.connectionTimeout ||
        DioExceptionType.sendTimeout ||
        DioExceptionType.receiveTimeout =>
          'Sunucuya ulaşılamadı (zaman aşımı).',
        DioExceptionType.connectionError => 'Ağ bağlantısı kurulamadı.',
        _ => 'Beklenmeyen bir hata oluştu.',
      },
      code: 'NETWORK_ERROR',
      statusCode: response?.statusCode,
    );
  }

  final String message;
  final String code;
  final int? statusCode;

  /// Doğrulama hatalarında alan bazlı mesajlar (`error.details`).
  final Map<String, dynamic> details;

  bool get isUnauthorized => statusCode == 401;

  @override
  String toString() => 'ApiException($code, $statusCode): $message';
}
