use std::fs;
use std::io;

fn main() {
    std::process::exit(real_main());
}

fn real_main() -> i32 {
    let args: Vec<_> = std::env::args().collect();
    if args.len() != 2 {
        println!("Usage: {} <zip file>", args[0]);
        return 1;
    }

    let zip_path = &args[1];
    let zip_file = fs::File::open(zip_path).unwrap();
    let mut archive = zip::ZipArchive::new(zip_file).unwrap();

    println!("Starting to decompress {} files", archive.len());
    for i in 0..archive.len() {
        println!("Processing file {} of {}", i + 1, archive.len());
        let mut file = archive.by_index(i).unwrap();
        let outpath = match file.enclosed_name() {
            Some(path) => {
                println!("Extracting to: {}", path.display());
                path.to_owned()
            }
            None => continue,
        };

        if file.name().ends_with('/') {
            println!("Creating directory: {}", outpath.display());
            fs::create_dir_all(&outpath).unwrap();
        } else {
            if let Some(p) = outpath.parent() {
                println!("Ensuring parent directory exists: {}", p.display());
                if !p.exists() {
                    fs::create_dir_all(p).unwrap();
                }
            }

            println!("Creating file: {}", outpath.display());
            if outpath.exists() {
                println!("File already exists: {}", outpath.display());
                continue;
            }
            let mut outfile = fs::File::create(&outpath).unwrap();
            println!("Copying file contents...");
            io::copy(&mut file, &mut outfile).unwrap();
            println!("Finished copying: {}", outpath.display());
        }
    }

    0
}
