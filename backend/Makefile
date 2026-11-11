.PHONY: tidy build run migrate seed migrate-legacy migrate-legacy-dry test

tidy:
	go mod tidy

build:
	CGO_ENABLED=0 go build -trimpath -ldflags "-s -w" -o bin/packsubmit ./cmd/server

run:
	go run ./cmd/server -config configs/config.yaml

migrate:
	go run ./cmd/server -config configs/config.yaml -migrate

seed:
	go run ./cmd/server -config configs/config.yaml -migrate -seed

migrate-legacy-dry:
	go run ./cmd/migrate-legacy -config configs/config.yaml -dry-run

migrate-legacy:
	go run ./cmd/migrate-legacy -config configs/config.yaml

test:
	go test ./...
