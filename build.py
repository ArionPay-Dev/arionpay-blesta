"""Build the Blesta gateway zip: copies the ArionPay PHP SDK into the gateway, then zips
   components/gateways/nonmerchant/arionpay/... plus README and TESTING.
   python3 build.py  ->  dist/arionpay-blesta-<version>.zip  (unzip in the Blesta main folder)"""
import json, os, shutil, zipfile, pathlib
HERE = pathlib.Path(__file__).resolve().parent
SDK = HERE.parent / "arionpay-php"
GW = HERE / "arionpay"

dest = GW / "lib" / "arionpay-php"
if dest.exists():
    shutil.rmtree(dest)
(dest / "src").mkdir(parents=True)
shutil.copy(SDK / "autoload.php", dest / "autoload.php")
shutil.copy(SDK / "LICENSE", dest / "LICENSE")
for f in sorted((SDK / "src").glob("*.php")):
    shutil.copy(f, dest / "src" / f.name)

version = json.loads((GW / "config.json").read_text())["version"]
(HERE / "dist").mkdir(exist_ok=True)
out = HERE / "dist" / f"arionpay-blesta-{version}.zip"
with zipfile.ZipFile(out, "w", zipfile.ZIP_DEFLATED) as z:
    for root, dirs, files in os.walk(GW):
        dirs.sort()
        for name in sorted(files):
            p = pathlib.Path(root) / name
            z.write(p, "components/gateways/nonmerchant/arionpay/" + str(p.relative_to(GW)))
    z.write(HERE / "README.md", "ARIONPAY-README.md")
    z.write(HERE / "TESTING.md", "ARIONPAY-TESTING.md")
print(out, os.path.getsize(out), "bytes")
