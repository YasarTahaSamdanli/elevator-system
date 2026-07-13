/// §12 sayfalı liste zarfı: `data` + `meta.pagination`.
class Paginated<T> {
  const Paginated({
    required this.items,
    required this.page,
    required this.perPage,
    required this.total,
    required this.totalPages,
  });

  factory Paginated.fromEnvelope(
    Map<String, dynamic> envelope,
    T Function(Map<String, dynamic> json) fromJson,
  ) {
    final items = (envelope['data'] as List<dynamic>? ?? const [])
        .cast<Map<String, dynamic>>()
        .map(fromJson)
        .toList();
    final pagination =
        ((envelope['meta'] as Map<String, dynamic>?)?['pagination']
                as Map<String, dynamic>?) ??
            const {};

    return Paginated(
      items: items,
      page: pagination['page'] as int? ?? 1,
      perPage: pagination['per_page'] as int? ?? items.length,
      total: pagination['total'] as int? ?? items.length,
      totalPages: pagination['total_pages'] as int? ?? 1,
    );
  }

  final List<T> items;
  final int page;
  final int perPage;
  final int total;
  final int totalPages;

  bool get hasMore => page < totalPages;
}
