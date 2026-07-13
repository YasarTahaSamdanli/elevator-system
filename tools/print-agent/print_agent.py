"""Asansor rapor yazdirma ajani.

Ofisteki PC'de calisir: backend'in print-jobs kuyrugunu yoklar, bekleyen
isin PDF'ini indirir, ilk 2 sayfasini siyah-beyaz olarak varsayilan (veya
config'te secilen) yaziciya basar ve isi done/failed olarak isaretler.

Yazdirma yontemi, ofiste yillardir calisan mail scriptinin kanitlanmis
pdfium+GDI yaklasiminin portudur (ham PDF'i RAW gondermek bazi yazicilarda
anlamsiz karakter basiyordu; sayfayi goruntuye cevirip surucuyle basmak
her yaziciyla calisir, AnyDesk Printer dahil).

Kurulum ve ayarlar icin README.md'ye bakin.
"""

import configparser
import os
import sys
import tempfile
import time

import requests
import win32con
import win32gui
import win32print
import win32ui
import pypdfium2 as pdfium
from PIL import ImageWin

CONFIG_PATH = os.path.join(os.path.dirname(os.path.abspath(__file__)), "config.ini")
PAGES_TO_PRINT = 2


def load_config():
    parser = configparser.ConfigParser()
    if not parser.read(CONFIG_PATH, encoding="utf-8"):
        sys.exit(f"config.ini bulunamadi: {CONFIG_PATH} (config.example.ini'yi kopyalayin)")

    section = parser["agent"]
    return {
        "api_url": section.get("api_url", "").rstrip("/"),
        "token": section.get("token", ""),
        "printer": section.get("printer", "") or None,
        "poll_seconds": section.getint("poll_seconds", 30),
    }


def api(cfg, method, path, **kwargs):
    response = requests.request(
        method,
        f"{cfg['api_url']}{path}",
        headers={
            "Accept": "application/json",
            "Authorization": f"Bearer {cfg['token']}",
        },
        timeout=60,
        **kwargs,
    )
    return response


def print_pdf(pdf_path, printer_name=None):
    """PDF'in ilk sayfalarini goruntuye cevirip GDI ile monokrom basar."""
    if printer_name is None:
        printer_name = win32print.GetDefaultPrinter()

    pdf = pdfium.PdfDocument(pdf_path)
    try:
        page_count = min(PAGES_TO_PRINT, len(pdf))

        # Yaziciyi siyah-beyaz moda alarak ac; renkli murekkep kullanilmaz.
        hprinter = win32print.OpenPrinter(printer_name)
        devmode = win32print.GetPrinter(hprinter, 2)["pDevMode"]
        win32print.ClosePrinter(hprinter)

        if devmode is not None:
            devmode.Color = 1  # DMCOLOR_MONOCHROME
            hdc = win32ui.CreateDCFromHandle(
                win32gui.CreateDC("WINSPOOL", printer_name, devmode)
            )
        else:
            hdc = win32ui.CreateDC()
            hdc.CreatePrinterDC(printer_name)

        area_w = hdc.GetDeviceCaps(win32con.HORZRES)
        area_h = hdc.GetDeviceCaps(win32con.VERTRES)
        dpi = hdc.GetDeviceCaps(win32con.LOGPIXELSX)

        hdc.StartDoc(f"AsansorRapor - {os.path.basename(pdf_path)}")
        for i in range(page_count):
            image = pdf[i].render(scale=dpi / 72).to_pil()

            # Sayfaya sigdir (en-boy oranini koruyarak).
            ratio = min(area_w / image.width, area_h / image.height)
            target_w = int(image.width * ratio)
            target_h = int(image.height * ratio)

            hdc.StartPage()
            dib = ImageWin.Dib(image.convert("L"))  # gri tonlama
            dib.draw(hdc.GetHandleOutput(), (0, 0, target_w, target_h))
            hdc.EndPage()
        hdc.EndDoc()
        hdc.DeleteDC()
    finally:
        pdf.close()

    print(f"Yaziciya gonderildi ({printer_name}): {os.path.basename(pdf_path)}")


def handle_job(cfg, job):
    uuid = job["uuid"]

    # Isi sahiplen; 422 baska bir ajan almis demektir, atla.
    claim = api(cfg, "PATCH", f"/print-jobs/{uuid}", json={"status": "printing"})
    if claim.status_code == 422:
        return
    claim.raise_for_status()

    try:
        pdf_response = api(cfg, "GET", f"/print-jobs/{uuid}/file")
        pdf_response.raise_for_status()

        fd, tmp_path = tempfile.mkstemp(suffix=".pdf")
        try:
            with os.fdopen(fd, "wb") as f:
                f.write(pdf_response.content)
            print_pdf(tmp_path, cfg["printer"])
        finally:
            os.unlink(tmp_path)

        api(cfg, "PATCH", f"/print-jobs/{uuid}", json={"status": "done"}).raise_for_status()
    except Exception as exc:  # yazdirma/indirme hatasi isi failed yapar
        print(f"Yazdirma hatasi ({uuid}): {exc}")
        api(
            cfg,
            "PATCH",
            f"/print-jobs/{uuid}",
            json={"status": "failed", "error_message": str(exc)[:1900]},
        )


def main():
    cfg = load_config()
    if not cfg["api_url"] or not cfg["token"]:
        sys.exit("config.ini icinde api_url ve token zorunludur.")

    print(f"Yazdirma ajani basladi: {cfg['api_url']} (her {cfg['poll_seconds']} sn)")

    while True:
        try:
            response = api(
                cfg, "GET", "/print-jobs?filter[status]=pending&per_page=10"
            )
            response.raise_for_status()
            jobs = response.json().get("data", [])

            for job in jobs:
                handle_job(cfg, job)
        except Exception as exc:
            print(f"Kuyruk kontrol hatasi: {exc}")

        time.sleep(cfg["poll_seconds"])


if __name__ == "__main__":
    main()
