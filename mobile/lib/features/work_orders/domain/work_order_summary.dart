/// `GET /work-orders/summary` yanıtı — ana ekran sayaçları.
///
/// Sayılar backend'de veritabanından hesaplanır; listedeki ilk sayfadan
/// türetilmez, bu yüzden kayıt sayısı sayfa boyutunu aşsa da doğrudur.
class WorkOrderSummary {
  const WorkOrderSummary({
    required this.date,
    required this.scheduledToday,
    required this.assigned,
    required this.inProgress,
    required this.completedToday,
  });

  factory WorkOrderSummary.fromJson(Map<String, dynamic> json) =>
      WorkOrderSummary(
        date: json['date'] as String? ?? '',
        scheduledToday: json['scheduled_today'] as int? ?? 0,
        assigned: json['assigned'] as int? ?? 0,
        inProgress: json['in_progress'] as int? ?? 0,
        completedToday: json['completed_today'] as int? ?? 0,
      );

  /// Sayıların hesaplandığı gün (YYYY-MM-DD, cihazın gönderdiği tarih).
  final String date;

  /// Bugüne planlanmış işler (iptal edilenler hariç, durumu ne olursa olsun).
  final int scheduledToday;

  /// Atanmış ama henüz başlanmamış işler (tarihten bağımsız).
  final int assigned;

  /// Şu an devam eden işler (tarihten bağımsız).
  final int inProgress;

  /// Bugün tamamlanan işler (tamamlanma saatine göre).
  final int completedToday;
}
