import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../config/app_config.dart';
import '../storage/token_storage.dart';
import 'api_exception.dart';

final apiClientProvider = Provider<ApiClient>((ref) {
  final dio = Dio(
    BaseOptions(
      baseUrl: AppConfig.apiBaseUrl,
      connectTimeout: const Duration(seconds: 10),
      receiveTimeout: const Duration(seconds: 20),
      headers: {'Accept': 'application/json'},
    ),
  );

  dio.interceptors.add(
    InterceptorsWrapper(
      onRequest: (options, handler) async {
        final token = await ref.read(tokenStorageProvider).read();
        if (token != null) {
          options.headers['Authorization'] = 'Bearer $token';
        }
        handler.next(options);
      },
      onError: (error, handler) async {
        // Süresi dolmuş / iptal edilmiş token: yerelden düş ki router
        // kullanıcıyı login ekranına yönlendirebilsin.
        if (error.response?.statusCode == 401 &&
            !error.requestOptions.path.contains('login')) {
          await ref.read(tokenStorageProvider).clear();
        }
        handler.next(error);
      },
    ),
  );

  return ApiClient(dio);
});

/// İnce HTTP sarmalayıcı: her çağrı çözülmüş §12 zarfını
/// (`{success, data, message, meta}`) döner ya da [ApiException] fırlatır.
class ApiClient {
  ApiClient(this._dio);

  final Dio _dio;

  Future<Map<String, dynamic>> get(
    String path, {
    Map<String, dynamic>? query,
  }) =>
      _send(() => _dio.get<Map<String, dynamic>>(path, queryParameters: query));

  Future<Map<String, dynamic>> post(String path, {Object? body}) =>
      _send(() => _dio.post<Map<String, dynamic>>(path, data: body));

  Future<Map<String, dynamic>> patch(String path, {Object? body}) =>
      _send(() => _dio.patch<Map<String, dynamic>>(path, data: body));

  Future<Map<String, dynamic>> delete(String path) =>
      _send(() => _dio.delete<Map<String, dynamic>>(path));

  Future<Map<String, dynamic>> _send(
    Future<Response<Map<String, dynamic>>> Function() request,
  ) async {
    try {
      final response = await request();

      return response.data ?? const {};
    } on DioException catch (exception) {
      throw ApiException.fromDio(exception);
    }
  }
}
