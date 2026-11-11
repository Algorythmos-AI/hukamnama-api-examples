// The Hukamnama from Sri Harmandir Sahib in Swift, checked against its hash.
//
//   swift swift/Hukamnama.swift
//
// Foundation and CryptoKit only (macOS 12+, iOS 15+). Set HUKAMNAMA_API to use another host.
// The decoder ignores keys it does not know: version 1 of the API only grows.
import CryptoKit
import Foundation

struct Line: Decodable {
    let id: Int
    let ang: Int
    let gurmukhi: String
    let translit: String
    let is_heading: Bool
    let is_rahao: Bool
}

struct Attribution: Decodable {
    let text: String
    let url: String
}

struct Day: Decodable {
    let amritsar_date: String
    let status: String
    let latest: String?            // on a pending answer: the most recent published date
    let ang: Int?
    let citation: String?
    let range_sha256: String?
    let lines: [Line]?
    let attribution: Attribution?
}

enum HukamnamaError: Error {
    case notProven                 // the lines do not match their hash: show nothing
    case http(Int)
}

let api = (ProcessInfo.processInfo.environment["HUKAMNAMA_API"] ?? "https://api.gurbanisoul.com")
    .trimmingCharacters(in: CharacterSet(charactersIn: "/"))

// No cache on disk: a command-line run always asks the API. (An app would keep a cache and send
// If-None-Match; the hash check below protects it either way.)
let session = URLSession(configuration: .ephemeral)

func get(_ path: String) async throws -> Day {
    var request = URLRequest(url: URL(string: api + path)!)
    request.setValue("hukamnama-api-examples (swift)", forHTTPHeaderField: "User-Agent")
    let (data, response) = try await session.data(for: request)
    let status = (response as? HTTPURLResponse)?.statusCode ?? 0
    guard status == 200 else { throw HukamnamaError.http(status) }
    return try JSONDecoder().decode(Day.self, from: data)
}

/// Throws unless the day's lines hash to its range_sha256.
func verify(_ day: Day) throws -> Day {
    guard let lines = day.lines, let want = day.range_sha256 else { throw HukamnamaError.notProven }
    let text = lines.map(\.gurmukhi).joined(separator: "\n")
    let hex = SHA256.hash(data: Data(text.utf8)).map { String(format: "%02x", $0) }.joined()
    guard hex == want else { throw HukamnamaError.notProven }
    return day
}

/// Today's Hukamnama, or the most recent one when today's is not published yet.
func latestPublished() async throws -> Day? {
    var day = try await get("/v1/hukamnama/today")
    if day.status != "published" {
        guard let latest = day.latest else { return nil }
        day = try await get("/v1/hukamnama/\(latest)")
        guard day.status == "published" else { return nil }
    }
    return try verify(day)
}

/// Call it today's only while its date is still the date in Amritsar (a fixed UTC+05:30).
func isToday(_ day: Day, now: Date = Date()) -> Bool {
    let formatter = DateFormatter()
    formatter.calendar = Calendar(identifier: .gregorian)
    formatter.locale = Locale(identifier: "en_US_POSIX")
    formatter.timeZone = TimeZone(secondsFromGMT: 5 * 3600 + 30 * 60)
    formatter.dateFormat = "yyyy-MM-dd"
    return formatter.string(from: now) == day.amritsar_date
}

do {
    guard let day = try await latestPublished() else {
        FileHandle.standardError.write(Data("No Hukamnama is available yet.\n".utf8))
        exit(2)
    }
    print("Hukamnama · Sri Harmandir Sahib")
    print("\(isToday(day) ? "Today's Hukamnama" : "Hukamnama"), \(day.amritsar_date) (Amritsar)\n")
    for line in day.lines ?? [] { print(line.gurmukhi) }     // every line, exactly as it arrived
    print("\n\(day.citation ?? "")")
    if let credit = day.attribution { print("\(credit.text): \(credit.url)") }
} catch HukamnamaError.notProven {
    FileHandle.standardError.write(Data("The Hukamnama could not be verified, so it is not shown. Please try again later.\n".utf8))
    exit(1)
}
