package sdk

import "encoding/json"

func jsonUnmarshalStrict(data []byte, v any) error {
	return json.Unmarshal(data, v)
}
