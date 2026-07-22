import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:url_launcher/url_launcher.dart';

import '../domain/work_order.dart';

/// İş emri detayındaki "Konum" butonunun açtığı sayfa: teknisyenin binaya
/// varması için gereken her şey tek ekranda — yazılı adres, kapı şifresi,
/// yönetici telefonu ve en altta yol tarifini başlatan buton.
class WorkOrderLocationSheet extends StatelessWidget {
  const WorkOrderLocationSheet({super.key, required this.location});

  final BuildingLocation location;

  static Future<void> show(BuildContext context, BuildingLocation location) {
    return showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      builder: (_) => WorkOrderLocationSheet(location: location),
    );
  }

  Future<void> _openDirections(BuildContext context) async {
    final messenger = ScaffoldMessenger.of(context);

    // Google Maps evrensel yol tarifi bağlantısı: cihazda Maps kuruluysa
    // uygulamada, değilse tarayıcıda açılır.
    final uri = Uri.parse(
      'https://www.google.com/maps/dir/?api=1'
      '&destination=${Uri.encodeComponent(location.navigationQuery)}',
    );

    final launched = await launchUrl(
      uri,
      mode: LaunchMode.externalApplication,
    );

    if (!launched) {
      messenger.showSnackBar(
        const SnackBar(content: Text('Harita uygulaması açılamadı.')),
      );
    }
  }

  Future<void> _call(BuildContext context, String phone) async {
    final messenger = ScaffoldMessenger.of(context);
    final uri = Uri(scheme: 'tel', path: phone.replaceAll(' ', ''));

    if (!await launchUrl(uri)) {
      messenger.showSnackBar(
        const SnackBar(content: Text('Arama başlatılamadı.')),
      );
    }
  }

  Future<void> _copy(BuildContext context, String value, String label) async {
    final messenger = ScaffoldMessenger.of(context);
    await Clipboard.setData(ClipboardData(text: value));
    messenger.showSnackBar(SnackBar(content: Text('$label kopyalandı.')));
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final address = location.fullAddress;
    final entranceCode = location.entranceCode;
    final managerPhone = location.managerPhone;

    return SafeArea(
      top: false,
      child: Padding(
        padding: const EdgeInsets.fromLTRB(20, 0, 20, 20),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              location.name ?? 'Bina Konumu',
              style: theme.textTheme.titleLarge,
            ),
            const SizedBox(height: 16),
            if (address.isNotEmpty)
              _InfoTile(
                icon: Icons.place_outlined,
                label: 'Adres',
                value: address,
                trailing: IconButton(
                  tooltip: 'Adresi kopyala',
                  onPressed: () => _copy(context, address, 'Adres'),
                  icon: const Icon(Icons.copy_outlined),
                ),
              ),
            if (entranceCode != null)
              _InfoTile(
                icon: Icons.lock_open_outlined,
                label: 'Kapı Şifresi',
                value: entranceCode,
                valueStyle: theme.textTheme.headlineSmall?.copyWith(
                  fontWeight: FontWeight.w700,
                  letterSpacing: 2,
                ),
                trailing: IconButton(
                  tooltip: 'Şifreyi kopyala',
                  onPressed: () =>
                      _copy(context, entranceCode, 'Kapı şifresi'),
                  icon: const Icon(Icons.copy_outlined),
                ),
              ),
            if (location.accessNotes != null)
              _InfoTile(
                icon: Icons.info_outline,
                label: 'Giriş Notu',
                value: location.accessNotes!,
              ),
            if (managerPhone != null)
              _InfoTile(
                icon: Icons.person_outline,
                label: location.managerName ?? 'Bina Yöneticisi',
                value: managerPhone,
                trailing: IconButton(
                  tooltip: 'Ara',
                  onPressed: () => _call(context, managerPhone),
                  icon: const Icon(Icons.call_outlined),
                ),
              ),
            if (!location.hasCoordinates && address.isNotEmpty)
              Padding(
                padding: const EdgeInsets.only(top: 4, bottom: 8),
                child: Text(
                  'Bu bina için konum işaretlenmemiş; yol tarifi adres '
                  'aramasıyla açılır.',
                  style: theme.textTheme.bodySmall,
                ),
              ),
            const SizedBox(height: 12),
            SizedBox(
              width: double.infinity,
              child: FilledButton.icon(
                onPressed: location.canNavigate
                    ? () => _openDirections(context)
                    : null,
                icon: const Icon(Icons.directions),
                label: const Text('Konuma Git'),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _InfoTile extends StatelessWidget {
  const _InfoTile({
    required this.icon,
    required this.label,
    required this.value,
    this.valueStyle,
    this.trailing,
  });

  final IconData icon;
  final String label;
  final String value;
  final TextStyle? valueStyle;
  final Widget? trailing;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 8),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Padding(
            padding: const EdgeInsets.only(top: 2),
            child: Icon(icon, color: theme.colorScheme.primary),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(label, style: theme.textTheme.labelMedium),
                const SizedBox(height: 2),
                SelectableText(
                  value,
                  style: valueStyle ?? theme.textTheme.bodyLarge,
                ),
              ],
            ),
          ),
          if (trailing != null) trailing!,
        ],
      ),
    );
  }
}
