import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_exception.dart';
import '../data/auth_repository.dart';
import '../domain/auth_user.dart';

/// Oturum durumu: `null` = giriş yapılmamış, [AuthUser] = aktif oturum.
/// Router bu provider'ı dinleyerek login/ana ekran yönlendirmesi yapar.
final authControllerProvider =
    AsyncNotifierProvider<AuthController, AuthUser?>(AuthController.new);

class AuthController extends AsyncNotifier<AuthUser?> {
  @override
  Future<AuthUser?> build() async {
    final repository = ref.watch(authRepositoryProvider);

    if (!await repository.hasToken()) {
      return null;
    }

    try {
      return await repository.me();
    } on ApiException {
      // Token geçersiz/süresi dolmuş: oturumsuz başla.
      return null;
    }
  }

  /// Hata durumunda [ApiException] fırlatır; login ekranı yakalayıp gösterir.
  Future<void> login(String email, String password) async {
    final repository = ref.read(authRepositoryProvider);

    await repository.login(email, password);
    state = AsyncData(await repository.me());
  }

  Future<void> logout() async {
    await ref.read(authRepositoryProvider).logout();
    state = const AsyncData(null);
  }
}
