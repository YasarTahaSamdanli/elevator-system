import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_client.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/storage/token_storage.dart';
import '../domain/auth_user.dart';

final authRepositoryProvider = Provider<AuthRepository>(
  (ref) => AuthRepository(
    ref.watch(apiClientProvider),
    ref.watch(tokenStorageProvider),
  ),
);

class AuthRepository {
  AuthRepository(this._api, this._tokens);

  final ApiClient _api;
  final TokenStorage _tokens;

  Future<void> login(String email, String password) async {
    final envelope = await _api.post(
      '/login',
      body: {'email': email, 'password': password},
    );
    final data = envelope['data'] as Map<String, dynamic>;

    await _tokens.write(data['token'] as String);
  }

  Future<AuthUser> me() async {
    final envelope = await _api.get('/me');

    return AuthUser.fromJson(envelope['data'] as Map<String, dynamic>);
  }

  Future<void> logout() async {
    try {
      await _api.post('/logout');
    } on ApiException {
      // Sunucuya ulaşılamasa bile yerel oturum kapanmalı.
    } finally {
      await _tokens.clear();
    }
  }

  Future<bool> hasToken() async => await _tokens.read() != null;
}
