/// `GET /me` yanıtındaki oturum sahibi kullanıcı.
class AuthUser {
  const AuthUser({
    required this.uuid,
    required this.name,
    required this.email,
    required this.companyName,
    required this.roles,
  });

  factory AuthUser.fromJson(Map<String, dynamic> json) => AuthUser(
        uuid: json['uuid'] as String,
        name: json['name'] as String,
        email: json['email'] as String,
        companyName:
            (json['company'] as Map<String, dynamic>?)?['name'] as String?,
        roles: (json['roles'] as List<dynamic>? ?? const []).cast<String>(),
      );

  final String uuid;
  final String name;
  final String email;
  final String? companyName;
  final List<String> roles;
}
